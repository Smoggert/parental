<?php

namespace Parental;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Illuminate\Support\Str;
use Illuminate\Events\Dispatcher;
use ReflectionException;

trait HasParent
{
    /**
     * @var bool
     */
    public $hasParent = true;

    /**
     * @return void
     * @throws ReflectionException
     */
    public static function bootHasParent(): void
    {
        // This adds support for using Parental with standalone Eloquent, outside a normal Laravel app.
        if (static::getEventDispatcher() === null) {
            static::setEventDispatcher(new Dispatcher());
        }

        static::creating(function ($model) {
            if ($model->parentHasHasChildrenTrait()) {
                $model->forceFill(
                    [$model->getInheritanceColumn() => $model->classToAlias(get_class($model))]
                );
            }
        });

        static::addGlobalScope(function ($query) {
            $instance = new static;

            if ($instance->parentHasHasChildrenTrait()) {
                $query->where($query->getModel()->getTable().'.'.$instance->getInheritanceColumn(), $instance->classToAlias(get_class($instance)));
            }
        });
    }

    /**
     * @return bool
     */
    public function parentHasHasChildrenTrait(): bool
    {
        return $this->hasChildren ?? false;
    }

    /**
     * @return string
     * @throws ReflectionException
     */
    public function getTable(): string
    {
        if (! isset($this->table)) {
            return str_replace('\\', '', Str::snake(Str::plural(class_basename($this->getParentClass()))));
        }

        return $this->table;
    }

    /**
     * @return string
     * @throws ReflectionException
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this->getParentClass())).'_'.$this->primaryKey;
    }

    /**
     * @param string $related
     * @param null|Model $instance
     * @return string
     * @throws ReflectionException
     */
    public function joiningTable($related, $instance = null): string
    {
        $relatedClassName = method_exists((new $related), 'getClassNameForRelationships')
            ? (new $related)->getClassNameForRelationships()
            : class_basename($related);

        $models = [
            Str::snake($relatedClassName),
            Str::snake($this->getClassNameForRelationships()),
        ];

        sort($models);

        return strtolower(implode('_', $models));
    }

    /**
     * @return string
     * @throws ReflectionException
     */
    public function getClassNameForRelationships(): string
    {
        return class_basename($this->getParentClass());
    }

    /**
     * Get the class name for polymorphic relations.
     *
     * @return string
     * @throws ReflectionException
     */
    public function getMorphClass(): string
    {
        $parentClass = $this->getParentClass();

        return (new $parentClass)->getMorphClass();
    }

    /**
     * Get the class name for poly-type collections
     *
     * @return string
     * @throws ReflectionException
     */
    public function getClassNameForSerialization(): string
    {
        return $this->getParentClass();
    }

    /**
     * Get the class name for Parent Class.
     *
     * @return string
     * @throws ReflectionException
     */
    protected function getParentClass(): string
    {
        static $parentClassName;

        return $parentClassName ?: $parentClassName = (new ReflectionClass($this))->getParentClass()->getName();
    }


    /**
     * Merge the fillable attributes for the model with Parent Class
     *
     * @return array<string>
     */
    public function getFillable()
    {
        // @todo currently failing when parent class is abstract
        // $parentFillable = (new \ReflectionClass((new \ReflectionClass($this))->getParentClass()));
        // if ($parentFillable->isAbstract()) {
        //     return ['*'];
        // }

        try {
            $parentClass = $this->getParentClass();
            $parentFillable = (new $parentClass)->getFillable();
            $arr = array_unique(array_merge($parentFillable, $this->fillable));
            return $arr;
        } catch (\Throwable $th) {
            return $this->fillable;
        }
    }
}
