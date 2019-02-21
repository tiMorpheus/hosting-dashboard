<?php

namespace Proxy\Util;

use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

class VarsFlashBag implements FlashBagInterface
{
    protected $name = 'vars';

    /**
     * Flash messages.
     *
     * @var array
     */
    protected $flashes = array();

    /**
     * The storage key for flashes in the session.
     *
     * @var string
     */
    protected $storageKey;

    /**
     * Constructor.
     *
     * @param string $storageKey The key used to store flashes in the session
     */
    public function __construct($storageKey = '_sf2_flashes_vars')
    {
        $this->storageKey = $storageKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(array &$flashes)
    {
        $this->flashes = &$flashes;
    }

    /**
     * {@inheritdoc}
     */
    public function add($type, $message)
    {
        // not used
    }

    /**
     * {@inheritdoc}
     */
    public function peek($type, array $default = array())
    {
        if (!$default) {
            $default = [null];
        }

        return $this->has($type) ? $this->flashes[$type] : $default[0];
    }

    /**
     * {@inheritdoc}
     */
    public function peekAll()
    {
        return $this->flashes;
    }

    /**
     * {@inheritdoc}
     */
    public function get($type, array $default = array())
    {
        if (!$default) {
            $default = [null];
        }

        if (!$this->has($type)) {
            return $default[0];
        }

        $return = $this->flashes[$type];

        unset($this->flashes[$type]);

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        $return = $this->peekAll();
        $this->flashes = array();

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function set($type, $var)
    {
        $this->flashes[$type] = (string) $var;
    }

    /**
     * {@inheritdoc}
     */
    public function setAll(array $vars)
    {
        $this->flashes = $vars;
    }

    /**
     * {@inheritdoc}
     */
    public function has($type)
    {
        return array_key_exists($type, $this->flashes) && $this->flashes[$type];
    }

    /**
     * {@inheritdoc}
     */
    public function keys()
    {
        return array_keys($this->flashes);
    }

    /**
     * {@inheritdoc}
     */
    public function getStorageKey()
    {
        return $this->storageKey;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->all();
    }
}
