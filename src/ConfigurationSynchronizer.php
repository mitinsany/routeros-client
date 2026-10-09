<?php

namespace MitinSany\RouterOS;

/**
 * Collects declarative RouterOS configuration entries and applies them to a client.
 */
class ConfigurationSynchronizer
{
    public $vars = array();
    public $logs = array();
    public $showIgnored = false;
    public $currentContext = '--internal--';

    private $configurations = array();
    private $ignoreRules = array();
    private $passRules = array();
    private $sections = array();
    private $commands = array();

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

            $path = array_shift($parts);
            $attributes = $this->parseAttributes($parts, $lineNumber + 1);
            if ($attributes === false) {
                return false;
            }

            if ($directive === 'config') {
                $this->config($path, $attributes);
            } elseif ($directive === 'ignore') {
                $this->ignore($path, $attributes);
            } elseif ($directive === 'pass') {
                $this->pass($path, $attributes);
            } else {
                return $this->error('Unknown directive at line ' . ($lineNumber + 1) . '.');
            }
        }

        return true;
    }

    public function replace($string)
    {
        $synchronizer = $this;

        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($synchronizer) {
            $value = $synchronizer->variable($matches[1]);

            return $value === null ? $matches[0] : $value;
        }, $string);
    }

    public function error($message)
    {
        $this->logs[] = $message;

        return false;
    }

    public function define($key, $value = null)
    {
        $this->vars[$key] = $value;

        return $value;
    }

    public function config($cmd, $line, $reverse = false)
    {
        if (!is_array($line)) {
            return $this->error('Configuration attributes must be an array.');
        }

        $this->configurations[] = array('cmd' => $cmd, 'line' => $line, 'reverse' => $reverse);

        return true;
    }

    public function cmd($alias, $cmd)
    {
        $this->commands[$alias] = $cmd;

        return true;
    }

    public function ignore($cmd, $line)
    {
        return $this->addRule($this->ignoreRules, $cmd, $line);
    }

    public function pass($cmd, $line)
    {
        return $this->addRule($this->passRules, $cmd, $line);
    }

    public function section($alias, $cmd, $type, $keys = false, $defaults = false)
    {
        $this->sections[$alias] = array(
            'cmd' => $cmd,
            'type' => $type,
            'keys' => $keys,
            'defaults' => $defaults
        );

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
        return $this->apply($conn, false);
    }

    public function updateSection($conn, $alias)
    {
        if (!isset($this->sections[$alias])) {
            return $this->error('Unknown configuration section: ' . $alias);
        }

        return $this->apply($conn, $this->sections[$alias]['cmd']);
    }

    private function parseAttributes(array $parts, $lineNumber)
    {
        $attributes = array();
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

    private function addRule(&$rules, $cmd, $line)
    {
        if (!is_array($line)) {
            return $this->error('Rule attributes must be an array.');
        }

        if (!isset($rules[$cmd])) {
            $rules[$cmd] = array();
        }

        $rules[$cmd][] = $line;

        return true;
    }

    private function apply($conn, $sectionPath)
    {
        if (!is_object($conn) || !method_exists($conn, 'set')) {
            return $this->error('RouterOS connection must provide set().');
        }

        foreach ($this->configurations as $configuration) {
            if ($sectionPath !== false && $configuration['cmd'] !== $sectionPath) {
                continue;
            }

            $attributes = $this->replaceAttributes($configuration['line']);
            $ignored = $this->isIgnored($configuration['cmd'], $attributes);
            $passed = $this->isPassed($configuration['cmd'], $attributes);
            if ($ignored || !$passed) {
                continue;
            }

            if ($conn->set($configuration['cmd'], $attributes) === false) {
                return $this->error('RouterOS rejected configuration for ' . $configuration['cmd'] . '.');
            }
        }

        return true;
    }

    private function replaceAttributes(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->replace($value);
        }

        return $attributes;
    }

    private function isIgnored($cmd, array $attributes)
    {
        if (!isset($this->ignoreRules[$cmd])) {
            return false;
        }

        foreach ($this->ignoreRules[$cmd] as $rule) {
            if ($this->matches($attributes, $this->replaceAttributes($rule))) {
                return true;
            }
        }

        return false;
    }

    private function isPassed($cmd, array $attributes)
    {
        if (!isset($this->passRules[$cmd])) {
            return true;
        }

        foreach ($this->passRules[$cmd] as $rule) {
            if ($this->matches($attributes, $this->replaceAttributes($rule))) {
                return true;
            }
        }

        return false;
    }

    private function matches(array $attributes, array $rule)
    {
        foreach ($rule as $key => $value) {
            if (!isset($attributes[$key]) || $attributes[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
