<?php

class CiiFile
{
    public function save($path)
    {
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $path))
            return false;

        return true;
    }

    public function __get($name)
    {
        if (isset($_FILES['file'][$name]))
            return $_FILES['file'][$name];

        return NULL;
    }

    public function getExtension()
    {
        $pathinfo = pathinfo($this->name);
        return $pathinfo['extension'];
    }
}