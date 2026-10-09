<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Tests\Support;

class SynchronizingRouter
{
    public $responses = [];
    public $records = [];
    public $failures = [];
    private $nextId = 1;

    public function getAll($path, $properties = false, $arguments = [])
    {
        $this->records[] = ['getAll', $path, $properties, $arguments];

        if (isset($this->failures['getAll'])) {
            return false;
        }

        return array_key_exists($path, $this->responses) ? $this->responses[$path] : [];
    }

    public function set($path, $attributes = [])
    {
        $this->records[] = ['set', $path, $attributes];

        return !isset($this->failures['set']);
    }

    public function add($path, $attributes = [])
    {
        $this->records[] = ['add', $path, $attributes];

        if (isset($this->failures['add'])) {
            return false;
        }

        return '*new' . $this->nextId++;
    }

    public function remove($path, $id)
    {
        $this->records[] = ['remove', $path, $id];

        return !isset($this->failures['remove']);
    }

    public function move($path, $id, $before)
    {
        $this->records[] = ['move', $path, $id, $before];

        return !isset($this->failures['move']);
    }

    public function mutatingRecords()
    {
        $records = [];
        foreach ($this->records as $record) {
            if ($record[0] !== 'getAll') {
                $records[] = $record;
            }
        }

        return $records;
    }
}
