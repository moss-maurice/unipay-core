<?php

namespace mmaurice\unipay\core\classes;

class Session
{
    protected $root;

    public function __construct($root)
    {
        $this->setRoot($root);
    }

    public function setRoot($root)
    {
        $this->root = $root;
    }

    public function getHost()
    {
        return $this->root;
    }

    public function get($host)
    {
        if (array_key_exists($host, $_SESSION[$this->root])) {
            return $_SESSION[$this->root][$host];
        }

        return null;
    }

    public function set($host, $value)
    {
        if (!array_key_exists($host, $_SESSION[$this->root])) {
            $_SESSION[$this->root][$host] = null;
        }

        $_SESSION[$this->root][$host] = $value;

        return true;
    }

    public function drop($host = null)
    {
        if (is_null($host)) {
            if (array_key_exists($this->root, $_SESSION)) {
                $_SESSION[$this->root] = null;

                unset($_SESSION[$this->root]);

                return true;
            }
        } else {
            if (array_key_exists($host, $_SESSION[$this->root])) {
                $_SESSION[$this->root][$host] = null;

                unset($_SESSION[$this->root][$host]);

                return true;
            }
        }

        return false;
    }

    public function has($host = null)
    {
        if (is_null($host)) {
            if (array_key_exists($this->root, $_SESSION)) {
                return true;
            }
        } else {
            if (array_key_exists($host, $_SESSION[$this->root])) {
                return true;
            }
        }

        return false;
    }

    public function empty($host)
    {
        return empty($_SESSION[$host]);
    }
}
