<?php

namespace MitinSany\RouterOS;

use MitinSany\RouterOS\Synchronization\DesiredRecordIndex;
use MitinSany\RouterOS\Synchronization\ValueComparison;

/**
 * Synchronizes declarative RouterOS configuration with a RouterOS client.
 */
class ConfigurationSynchronizer
{
    public $vars = [];
    public $logs = [];
    public $errors = [];
    public $showIgnored = false;
    public $currentContext = '--internal--';

    private $configurations = [];
    private $ignoreRules = [];
    private $passRules = [];
    private $sections = [];
    private $commands = [];
    private $continueOnError = false;

    public function __get($name)
    {
        return isset($this->vars[$name]) ? $this->vars[$name] : null;
    }

    public function __set($name, $value)
    {
        if ($value === null) {
            unset($this->vars[$name]);

            return;
        }

        $this->vars[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->vars[$name]);
    }

    public function __unset($name)
    {
        unset($this->vars[$name]);
    }

    public function variable($key)
    {
        return isset($this->vars[$key]) ? $this->vars[$key] : null;
    }

    public function configureWithArguments($cmd)
    {
        $arguments = func_get_args();
        array_shift($arguments);

        return $this->config($cmd, $arguments);
    }

    public function configureReversedWithArguments($cmd)
    {
        $arguments = func_get_args();
        array_shift($arguments);

        return $this->config($cmd, $arguments, true);
    }

    public function ignoreWithArguments($cmd)
    {
        $arguments = func_get_args();
        array_shift($arguments);

        return $this->ignore($cmd, $arguments);
    }

    public function passWithArguments($cmd)
    {
        $arguments = func_get_args();
        array_shift($arguments);

        return $this->pass($cmd, $arguments);
    }

    public function parseFile($file)
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return $this->error('Unable to read configuration file: ' . $file);
        }

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '' || substr($line, 0, 1) === '#') {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            $directive = array_shift($parts);
            if (count($parts) === 0) {
                return $this->error('Missing arguments at line ' . ($lineNumber + 1) . '.');
            }

            if ($directive === 'define') {
                $key = array_shift($parts);
                $this->define($key, implode(' ', $parts));
                continue;
            }

            $reference = array_shift($parts);
            $attributes = $this->parseAttributes($parts, $lineNumber + 1);
            if ($attributes === false) {
                return false;
            }

            if ($directive === 'config') {
                $this->config($reference, $attributes);
            } elseif ($directive === 'ignore') {
                $this->ignore($reference, $attributes);
            } elseif ($directive === 'pass') {
                $this->pass($reference, $attributes);
            } else {
                return $this->error('Unknown directive at line ' . ($lineNumber + 1) . '.');
            }
        }

        return true;
    }

    public function replace($string)
    {
        if (!is_string($string)) {
            return $string;
        }

        $synchronizer = $this;

        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($synchronizer) {
            $value = $synchronizer->variable($matches[1]);

            return $value === null ? $matches[0] : $value;
        }, $string);
    }

    public function error($message)
    {
        $this->logs[] = $message;
        $this->errors[] = $message;

        return false;
    }

    /**
     * Keeps reconciling independent records and sections after RouterOS operation failures.
     *
     * Callers can inspect the public $errors list after update().
     */
    public function continueOnError($enabled = true)
    {
        $this->continueOnError = (bool) $enabled;

        return $this;
    }

    public function define($key, $value = null)
    {
        $this->vars[$key] = $value;

        return $value;
    }

    public function config($reference, $attributes, $reverse = false)
    {
        $attributes = $this->normaliseAttributes($attributes);
        if ($attributes === false) {
            return $this->error('Configuration attributes must be an array.');
        }

        $key = $this->referenceKey($reference);
        if (!isset($this->configurations[$key])) {
            $this->configurations[$key] = [];
        }

        if ($reverse) {
            array_unshift($this->configurations[$key], $attributes);
        } else {
            $this->configurations[$key][] = $attributes;
        }

        return true;
    }

    public function cmd($alias, $cmd)
    {
        $this->commands[$alias] = $cmd;

        return true;
    }

    public function ignore($reference, $attributes)
    {
        return $this->addRule($this->ignoreRules, $this->referenceKey($reference), $attributes);
    }

    public function pass($reference, $attributes)
    {
        return $this->addRule($this->passRules, $this->referenceKey($reference), $attributes);
    }

    public function section($alias, $path, $type, $keys = false, $defaults = false)
    {
        $allowedTypes = ['value', 'set', 'addset', 'addset_order'];
        if (!in_array($type, $allowedTypes, true)) {
            return $this->error('Unknown configuration section type: ' . $type);
        }

        $normalisedDefaults = $this->normaliseAttributes($defaults === false ? [] : $defaults);
        if ($normalisedDefaults === false) {
            return $this->error('Section defaults must be an array.');
        }

        $this->sections[$alias] = [
            'path' => $path,
            'type' => $type,
            'keys' => $this->normaliseKeys($keys),
            'defaults' => $normalisedDefaults
        ];

        return true;
    }

    /**
     * Limits the properties and records read for one configuration section.
     *
     * Query words must be complete RouterOS API words such as ?comment=sync.
     */
    public function readOptions($reference, $proplist = false, $queryWords = [])
    {
        $alias = $this->sectionForReference($reference);
        if ($alias === false) {
            return $this->error('Unknown configuration section: ' . $reference);
        }
        if ($proplist !== false && !is_array($proplist) && !is_string($proplist)) {
            return $this->error('Read properties must be an array or string.');
        }
        if (!is_array($queryWords)) {
            return $this->error('Query words must be an array.');
        }
        foreach ($queryWords as $queryWord) {
            if (!is_string($queryWord) || substr($queryWord, 0, 1) !== '?') {
                return $this->error('Query words must begin with ?.');
            }
        }

        $this->sections[$alias]['readOptions'] = [
            'proplist' => $proplist,
            'queryWords' => array_values($queryWords)
        ];

        return true;
    }

    public function call($cmd, $args)
    {
        if (!isset($this->commands[$cmd])) {
            return $this->error('Unknown configuration command: ' . $cmd);
        }

        return $this->config($this->commands[$cmd], $args);
    }

    public function update($conn, $ret = false)
    {
        $this->logs = [];
        $this->errors = [];

        foreach ($this->sections as $alias => $section) {
            if (!$this->updateSection($conn, $alias)) {
                return false;
            }
        }

        foreach ($this->configurations as $key => $configurations) {
            if ($this->sectionForReference($key) !== false) {
                continue;
            }
            if (!$this->updateStandalone($conn, $key, $configurations)) {
                return false;
            }
        }

        return $this->continueOnError || count($this->errors) === 0;
    }

    public function updateSection($conn, $reference)
    {
        $alias = $this->sectionForReference($reference);
        if ($alias === false) {
            return $this->error('Unknown configuration section: ' . $reference);
        }
        if (!is_object($conn) || !method_exists($conn, 'getAll')) {
            return $this->error('RouterOS connection must provide getAll().');
        }

        $section = $this->sections[$alias];
        if (isset($section['readOptions'])) {
            $current = $conn->getAll(
                $section['path'],
                $section['readOptions']['proplist'],
                [],
                false,
                false,
                $section['readOptions']['queryWords']
            );
        } else {
            $current = $conn->getAll($section['path']);
        }
        if ($current === false) {
            return $this->error('getAll failed ' . $section['path']);
        }

        if ($section['type'] === 'value') {
            return $this->updateValueSection($conn, $alias, $section, $current);
        }

        return $this->updateListSection($conn, $alias, $section, $this->normaliseRecords($current));
    }

    private function updateStandalone($conn, $key, array $configurations)
    {
        if (!is_object($conn) || !method_exists($conn, 'set')) {
            return $this->error('RouterOS connection must provide set().');
        }

        $path = $this->pathFromKey($key);
        foreach ($configurations as $configuration) {
            $attributes = $this->replaceAttributes($configuration);
            if ($this->isIgnored($key, $attributes)) {
                $this->log('ignore', $path);
                continue;
            }
            if (!$this->isPassed($key, $attributes)) {
                $this->log('nopass', $path);
                continue;
            }
            if ($conn->set($path, $attributes) === false) {
                if (!$this->operationFailed('set', $path, null, $conn)) {
                    return false;
                }
                continue;
            }
            $this->log('set', $path);
        }

        return true;
    }

    private function updateValueSection($conn, $alias, array $section, $current)
    {
        if (!is_object($conn) || !method_exists($conn, 'set')) {
            return $this->error('RouterOS connection must provide set().');
        }

        $desired = [];
        foreach ($this->sectionConfigurations($alias) as $configuration) {
            $configuration = $this->desiredAttributes($section, $configuration);
            if ($this->isIgnored($alias, $configuration)) {
                $this->log('ignore', $section['path']);
                continue;
            }
            if (!$this->isPassed($alias, $configuration)) {
                $this->log('nopass', $section['path']);
                continue;
            }
            $desired = array_merge($desired, $configuration);
        }

        if (count($desired) === 0) {
            return true;
        }

        $records = $this->normaliseRecords($current);
        if (count($records) === 0) {
            return true;
        }

        $changes = ValueComparison::changes($records[0], $desired);
        if (count($changes) === 0) {
            return true;
        }
        if ($conn->set($section['path'], $changes) === false) {
            return $this->operationFailed('set', $section['path'], null, $conn);
        }
        $this->log('set', $section['path']);

        return true;
    }

    private function updateListSection($conn, $alias, array $section, array $current)
    {
        $type = $section['type'];
        if (!is_object($conn) || !method_exists($conn, 'set')) {
            return $this->error('RouterOS connection must provide set().');
        }

        $desired = [];
        foreach ($this->sectionConfigurations($alias) as $configuration) {
            $configuration = $this->desiredAttributes($section, $configuration);
            if ($this->isIgnored($alias, $configuration)) {
                $this->log('ignore', $section['path']);
                continue;
            }
            if (!$this->isPassed($alias, $configuration)) {
                $this->log('nopass', $section['path']);
                continue;
            }
            $desired[] = $configuration;
        }

        $desiredIndex = new DesiredRecordIndex($desired, $section['keys']);
        $matched = [];
        $currentOrder = [];
        foreach ($current as $record) {
            $id = isset($record['.id']) ? $record['.id'] : null;
            if ($this->isIgnored($alias, $record)) {
                $this->log('ignore', $section['path'], $id);
                continue;
            }
            if (!$this->isPassed($alias, $record)) {
                $this->log('nopass', $section['path'], $id);
                continue;
            }

            $index = $desiredIndex->find($record, $matched);
            if ($index !== false) {
                if ($this->isDynamic($record)) {
                    if (!$this->remove($conn, $section['path'], $id) && !$this->continueOnError) {
                        return false;
                    }
                    continue;
                }
                $matched[$index] = $id;
                if ($id !== null) {
                    $currentOrder[] = $id;
                }
                $changes = ValueComparison::changes($record, $desired[$index]);
                if (count($changes) > 0) {
                    $changes['.id'] = $id;
                    if ($conn->set($section['path'], $changes) === false) {
                        if (!$this->operationFailed('set', $section['path'], $id, $conn)) {
                            return false;
                        }
                        continue;
                    }
                    $this->log('set', $section['path'], $id);
                }
                continue;
            }

            if (($type === 'addset' || $type === 'addset_order') && $this->mayRemove($alias, $section, $record)) {
                if (!$this->remove($conn, $section['path'], $id) && !$this->continueOnError) {
                    return false;
                }
            }
        }

        if ($type === 'addset' || $type === 'addset_order') {
            if (!$this->addMissing($conn, $section, $desired, $matched)) {
                return false;
            }
        }

        if ($type === 'addset_order') {
            return $this->orderRecords($conn, $section['path'], $matched, $currentOrder);
        }

        return true;
    }

    private function addMissing($conn, array $section, array $desired, array &$matched)
    {
        if (!method_exists($conn, 'add')) {
            return $this->error('RouterOS connection must provide add().');
        }

        foreach ($desired as $index => $attributes) {
            if (isset($matched[$index])) {
                continue;
            }
            if ($section['type'] === 'addset_order') {
                $before = $this->nextMatchedId($matched, $index, count($desired));
                if ($before !== null) {
                    $attributes['place-before'] = $before;
                }
            }
            $result = $conn->add($section['path'], $attributes);
            if ($result === false) {
                if (!$this->operationFailed('add', $section['path'], null, $conn)) {
                    return false;
                }
                continue;
            }
            if (is_string($result) && $result !== '') {
                $matched[$index] = $result;
            }
            $this->log('add', $section['path']);
        }

        return true;
    }

    private function orderRecords($conn, $path, array $matched, array $currentOrder)
    {
        if (!method_exists($conn, 'move')) {
            return $this->error('RouterOS connection must provide move().');
        }

        ksort($matched);
        $desiredOrder = [];
        foreach ($matched as $id) {
            if (is_string($id) && $id !== '') {
                $desiredOrder[] = $id;
            }
        }

        foreach ($desiredOrder as $position => $id) {
            if (isset($currentOrder[$position]) && $currentOrder[$position] === $id) {
                continue;
            }
            $found = array_search($id, $currentOrder, true);
            if ($found === false || !isset($currentOrder[$position])) {
                continue;
            }
            $before = $currentOrder[$position];
            if ($conn->move($path, $id, $before) === false) {
                if (!$this->operationFailed('move', $path, $id, $conn)) {
                    return false;
                }
                continue;
            }
            array_splice($currentOrder, $found, 1);
            array_splice($currentOrder, $position, 0, [$id]);
            $this->log('move', $path, $id, $before);
        }

        return true;
    }

    private function remove($conn, $path, $id)
    {
        if ($id === null || !method_exists($conn, 'remove')) {
            return $this->error('RouterOS connection must provide remove().');
        }
        if ($conn->remove($path, $id) === false) {
            return $this->operationFailed('remove', $path, $id, $conn);
        }
        $this->log('remove', $path, $id);

        return true;
    }

    private function mayRemove($alias, array $section, array $record)
    {
        if ($this->isDefault($record) || $this->isDynamic($record)) {
            return false;
        }
        if (isset($this->passRules[$alias])) {
            return $this->isPassed($alias, $record);
        }
        if (count($section['defaults']) > 0) {
            return $this->matches($record, $this->replaceAttributes($section['defaults']));
        }

        return false;
    }

    private function desiredAttributes(array $section, array $configuration)
    {
        return $this->replaceAttributes(array_merge($section['defaults'], $configuration));
    }

    private function sectionConfigurations($alias)
    {
        return isset($this->configurations[$alias]) ? $this->configurations[$alias] : [];
    }

    private function referenceKey($reference)
    {
        $alias = $this->sectionForReference($reference);

        return $alias === false ? 'path:' . $reference : $alias;
    }

    private function sectionForReference($reference)
    {
        if (isset($this->sections[$reference])) {
            return $reference;
        }
        foreach ($this->sections as $alias => $section) {
            if ($section['path'] === $reference) {
                return $alias;
            }
        }

        return false;
    }

    private function pathFromKey($key)
    {
        return substr($key, 0, 5) === 'path:' ? substr($key, 5) : $key;
    }

    private function normaliseKeys($keys)
    {
        if ($keys === false || $keys === null || $keys === '') {
            return [];
        }
        if (is_array($keys)) {
            return array_values($keys);
        }

        return preg_split('/\s*,\s*/', $keys);
    }

    private function normaliseAttributes($attributes)
    {
        if ($attributes === false) {
            return [];
        }
        if (!is_array($attributes)) {
            return false;
        }

        $normalised = [];
        foreach ($attributes as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $pair = explode('=', $value, 2);
                if (count($pair) !== 2 || $pair[0] === '') {
                    return false;
                }
                $normalised[$pair[0]] = $pair[1];
            } else {
                $normalised[$key] = $value;
            }
        }

        return $normalised;
    }

    private function normaliseRecords($records)
    {
        if (!is_array($records) || count($records) === 0) {
            return [];
        }
        $first = reset($records);
        if (!is_array($first)) {
            return [$records];
        }

        return $records;
    }

    private function parseAttributes(array $parts, $lineNumber)
    {
        $attributes = [];
        foreach ($parts as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2 || $pair[0] === '') {
                $this->error('Invalid attribute at line ' . $lineNumber . '.');

                return false;
            }
            $attributes[$pair[0]] = $pair[1];
        }

        return $attributes;
    }

    private function addRule(&$rules, $key, $attributes)
    {
        $attributes = $this->normaliseAttributes($attributes);
        if ($attributes === false) {
            return $this->error('Rule attributes must be an array.');
        }
        if (!isset($rules[$key])) {
            $rules[$key] = [];
        }
        $rules[$key][] = $attributes;

        return true;
    }

    private function replaceAttributes(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->replace($value);
        }

        return $attributes;
    }

    private function isIgnored($key, array $attributes)
    {
        return $this->matchesRules($this->ignoreRules, $key, $attributes);
    }

    private function isPassed($key, array $attributes)
    {
        if (!isset($this->passRules[$key])) {
            return true;
        }

        return $this->matchesRules($this->passRules, $key, $attributes);
    }

    private function matchesRules(array $rules, $key, array $attributes)
    {
        if (!isset($rules[$key])) {
            return false;
        }
        foreach ($rules[$key] as $rule) {
            if ($this->matches($attributes, $this->replaceAttributes($rule))) {
                return true;
            }
        }

        return false;
    }

    private function matches(array $attributes, array $rule)
    {
        foreach ($rule as $key => $value) {
            if (!array_key_exists($key, $attributes) || !ValueComparison::equivalent($attributes[$key], $value)) {
                return false;
            }
        }

        return true;
    }

    private function isDefault(array $record)
    {
        return isset($record['default']) && ValueComparison::normalise($record['default']) === 'true';
    }

    private function isDynamic(array $record)
    {
        return isset($record['dynamic']) && ValueComparison::normalise($record['dynamic']) === 'true';
    }

    private function nextMatchedId(array $matched, $index, $count)
    {
        for ($position = $index + 1; $position < $count; $position++) {
            if (isset($matched[$position]) && is_string($matched[$position]) && $matched[$position] !== '') {
                return $matched[$position];
            }
        }

        return null;
    }

    /** @return bool Whether reconciliation may continue after this mutation failure. */
    private function operationFailed($operation, $path, $id = null, $conn = null)
    {
        $message = $operation . ' failed ' . $path . ($id === null ? '' : ' ' . $id);
        if (
            is_object($conn) && isset($conn->errorString) && is_string($conn->errorString)
            && $conn->errorString !== ''
        ) {
            $message .= ': ' . $conn->errorString;
        }

        $this->error($message);

        return $this->continueOnError;
    }

    private function log($operation, $path, $id = null, $before = null)
    {
        $message = $operation . ' ' . $path;
        if ($id !== null) {
            $message .= ' ' . $id;
        }
        if ($before !== null) {
            $message .= ' ' . $before;
        }
        $this->logs[] = $message;
    }
}
