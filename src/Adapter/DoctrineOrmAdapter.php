<?php

namespace SmtLab\JsonApiBundle\Adapter;

use Doctrine\ORM\QueryBuilder;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Deferred;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\HasMany;
use Doctrine\ORM\EntityManagerInterface;
use Tobyz\JsonApiServer\Schema\Attribute;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Tobyz\JsonApiServer\Schema\Relationship;
use Tobyz\JsonApiServer\Adapter\AdapterInterface;

class DoctrineOrmAdapter implements AdapterInterface
{
    private $entityClass;

    private $em;

    private $name;

    public function __construct($entityClass, EntityManagerInterface $em)
    {
        $this->entityClass = $entityClass;
        $this->name = str_replace('App\Entity\\', '', $entityClass);
        $this->em = $em;
    }

    /**
     * Create a new query builder instance.
     *
     * This is used as a basis for building the queries which show a resource
     * or list a resource index. It will be passed around through the relevant
     * scopes, filters, and sorting methods before finally being passed into
     * the `find` or `get` methods.
     */
    public function query(): QueryBuilder
    {
        return $this->em->getRepository($this->entityClass)
            ->createQueryBuilder($this->name);
    }

    /**
     * Manipulate the query to only include resources with the given IDs.
     * @param $query QueryBuilder
     */
    public function filterByIds($query, array $ids): void
    {
        $query->where($this->name . '.id = :id');
        $query->setParameter('id', $ids);
    }

    /**
     * Manipulate the query to only include resources with a certain attribute
     * value.
     *
     * @param string $operator The operator to use for comparison: = < > <= >=
     */
    public function filterByAttribute($query, Attribute $attribute, $value, string $operator = '='): void
    {
        $query->where($this->name . '.' . $attribute->getName() . ' ' . $operator . ' :' . $attribute->getName());
        $query->setParameter($attribute->getName(), $value);
    }

    /**
     * Manipulate the query to only include resources with a relationship within
     * the given scope.
     * @param QueryBuilder $query
     */
    public function filterByRelationship($query, Relationship $relationship, \Closure $scope): void
    {
        $query->join($this->name . '.' . $relationship->getProperty(), $relationship->getName());
        $scope($query);
    }

    /**
     * Manipulate the query to sort by the given attribute in the given direction.
     * @param QueryBuilder $query
     */
    public function sortByAttribute($query, Attribute $attribute, string $direction): void
    {
        $query->orderBy($attribute, $direction);
    }

    /**
     * Manipulate the query to only include a certain number of results,
     * starting from the given offset.
     * @param QueryBuilder $query
     */
    public function paginate($query, int $limit, int $offset): void
    {
        $query->setMaxResults($limit)->setFirstResult($offset);
    }

    /**
     * Find a single resource by ID from the query.
     * @param QueryBuilder $query
     */
    public function find($query, string $id)
    {
        try {
            return $query->where($this->name . '.id = :id')
                ->setParameter(':id', $id)
                ->getQuery()
                ->getSingleResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            return null;
        }
    }

    /**
     * Get a list of resources from the query.
     * @param QueryBuilder $query
     */
    public function get($query): array
    {
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        $data = [];
        if ($paginator->count() === 0) {
            return $data;
        }
        array_push($data, ...$paginator);

        return $data;
    }

    /**
     * Get the number of results from the query.
     * @param QueryBuilder $query
     */
    public function count($query): int
    {
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        return $paginator->count();
    }

    /**
     * Get the ID from the model.
     */
    public function getId($model): string
    {
        return $model->getId();
    }

    /**
     * Get the value of an attribute from the model.
     *
     * @return mixed|Deferred
     */
    public function getAttribute($model, Attribute $attribute)
    {
        return $model->{'get' . $this->snakeCaseToCamelCase($attribute->getName())}();
    }

    /**
     * Get the model for a has-one relationship for the model.
     *
     * @return mixed|null|Deferred
     */
    public function getHasOne($model, HasOne $relationship, bool $linkageOnly, Context $context)
    {
        return $model->{'get' . $relationship->getProperty()}();
    }

    /**
     * Get a list of models for a has-many relationship for the model.
     *
     * @return array|Deferred
     */
    public function getHasMany($model, HasMany $relationship, bool $linkageOnly, Context $context)
    {
        $items = $model->{'get' . $relationship->getProperty()}();

        return $items->count() > 1 ? $items->toArray() : [];
    }

    /**
     * Determine whether this resource type represents the given model.
     *
     * This is used for polymorphic relationships, where there are one or many
     * related models of unknown type. The first resource type with an adapter
     * that responds positively from this method will be used.
     * 
     * @param QueryBuilder $query
     */
    public function represents($model): bool
    {
        return $model instanceof $this->entityClass;
    }

    /**
     * Create a new model instance.
     */
    public function model()
    {
        return new $this->entityClass;
    }

    /**
     * Apply a user-generated ID to the model.
     */
    public function setId($model, string $id): void
    {
    }

    /**
     * Apply an attribute value to the model.
     */
    public function setAttribute($model, Attribute $attribute, $value): void
    {
        $model->{'set' . $this->snakeCaseToCamelCase($attribute->getName())}($value);
    }

    /**
     * Apply a has-one relationship value to the model.
     */
    public function setHasOne($model, HasOne $relationship, $related): void
    {
        $model->{'set' . $this->snakeCaseToCamelCase($relationship->getName(), true, '-')}($related);
    }

    /**
     * Save the model.
     */
    public function save($model): void
    {
        $this->em->persist($model);
        $this->em->flush();
    }

    /**
     * Save a has-many relationship for the model.
     */
    public function saveHasMany($model, HasMany $relationship, array $related): void
    {
        foreach ($model->{'get' . $this->snakeCaseToCamelCase($relationship->getType(), true, '-')}() as $relation) {
            $this->em->persist($relation);
        }
        $this->em->flush();
    }

    /**
     * Delete the model.
     */
    public function delete($model): void
    {
        $this->em->remove($model);
        $this->em->flush();
    }

    private function snakeCaseToCamelCase($string, $capitalizeFirstCharacter = true, $separator = '_')
    {
        $str = str_replace(' ', '', ucwords(str_replace($separator, ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }

    public function setAlias(?string $alias)
    {
        $this->relationAlias = $alias;
    }
}
