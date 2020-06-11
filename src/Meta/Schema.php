<?php

/**
 *
 *
 */

namespace CliGenerator\Meta;

/**
 *
 *
 */
interface Schema
{
    /**
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function connection();

    /**
     * @return string
     */
    public function schema();

    /**
     * @return \CliGenerator\Meta\Blueprint[]
     */
    public function tables();

    /**
     * @param string $table
     *
     * @return bool
     */
    public function has($table);

    /**
     * @param string $table
     *
     * @return \CliGenerator\Meta\Blueprint
     */
    public function table($table);

    /**
     * @param \CliGenerator\Meta\Blueprint $table
     *
     * @return array
     */
    public function referencing(Blueprint $table);
}
