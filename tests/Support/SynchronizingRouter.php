<?php

namespace MitinSany\RouterOS\Tests\Support;

class SynchronizingRouter
{
    public $responses = array();
    public $records = array();
    public $failures = array();
    private $nextId = 1;

    public function getAll($path, $properties = false, $arguments = array())
    {
        $this->records[] = array('getAll', $path, $properties, $arguments);

        if (isset($this->failures['getAll'])) {
            return false;
        }

        return array_key_exists($path, $this->responses) ? $this->responses[$path] : array();
    }

    public function set($path, $attributes = array())
    {
        $this->records[] = array('set', $path, $attributes);

        return !isset($this->failures['set']);
    }

    public function add($path, $attributes = array())
    {
        $this->records[] = array('add', $path, $attributes);

        if (isset($this->failures['add'])) {
            return false;
        }

        return '*new' . $this->nextId++;
    }

    public function remove($path, $id)
    {
        $this->records[] = array('remove', $path, $id);

        return !isset($this->failures['remove']);
    }

    public function move($path, $id, $before)
    {
        $this->records[] = array('move', $path, $id, $before);

        return !isset($this->failures['move']);
    }

    public function mutatingRecords()
    {
        $records = array();
        foreach ($this->records as $record) {
            if ($record[0] !== 'getAll') {
                $records[] = $record;
            }
        }

        return $records;
    }
}
