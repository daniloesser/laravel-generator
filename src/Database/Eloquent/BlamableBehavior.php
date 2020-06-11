<?php

/**
 *
 *
 */

namespace CliGenerator\Database\Eloquent;

trait BlamableBehavior
{
    /**
     * Boot Blamable Behaviour trait for a model.
     */
    public static function bootBlamableBehavior()
    {
        static::observe(WhoDidIt::class);
    }
}
