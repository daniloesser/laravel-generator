<?php

/**
 *
 *
 */

namespace CliGenerator\Code\Model\Relations;

use Illuminate\Support\Str;

class HasOne extends HasOneOrMany
{
    /**
     * @return string
     */
    public function hint()
    {
        return $this->related->getQualifiedUserClassName();
    }

    /**
     * @return string
     */
    public function name()
    {
        if ($this->parent->usesSnakeAttributes()) {
            return Str::snake($this->related->getClassName());
        }

        return Str::camel($this->related->getClassName());
    }

    /**
     * @return string
     */
    public function method()
    {
        return 'hasOne';
    }
}
