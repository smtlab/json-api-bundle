<?php

namespace SmtLab\JsonApiServerBundle;

use Exception;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Nyholm\Psr7\Factory\Psr17Factory;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use SmtLab\JsonApiServerBundle\Adapter\DoctrineOrmAdapter;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;

class JsonApiServer
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @todo configurable api url, entity namesapce, includable, filterable, filter operators
     * @todo custom scopes
     */
    public function serve(Request $request)
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $psrRequest = $psrHttpFactory->createRequest($request);

        $api = new JsonApi('/api/v1');

        /** @var ClassMetaData[] */
        $metas = $this->em->getMetadataFactory()->getAllMetadata();

        foreach ($metas as $meta) {
            $api->resourceType(
                str_replace('App\Entity\\', '', $meta->getName()),
                new DoctrineOrmAdapter($meta->getName(), $this->em),
                function (Type $type) use ($meta) {

                    $type->creatable();

                    $type->updatable();

                    foreach ($meta->getFieldNames() as $attribute) {
                        if ($attribute === $meta->getSingleIdentifierFieldName()) {
                            continue;
                        }
                        $type->attribute($attribute)->filterable()->writable();
                    }

                    foreach ($meta->getAssociationMappings() as $association) {
                        if (in_array($association['type'], [ClassMetadataInfo::ONE_TO_ONE, ClassMetadataInfo::MANY_TO_ONE])) {
                            $type->hasOne(str_replace('App\Entity\\', '', $association['targetEntity']))
                                ->property($association['fieldName'])
                                ->filterable()
                                ->includable();
                        }
                        if (in_array($association['type'], [ClassMetadataInfo::ONE_TO_MANY, ClassMetadataInfo::MANY_TO_MANY, ClassMetadataInfo::TO_MANY])) {
                            $type->hasMany(str_replace('App\Entity\\', '', $association['targetEntity']))
                                ->property($association['fieldName'])->filterable()->includable();
                        }
                    }
                }
            );
        }

        try {
            $response = $api->handle($psrRequest);
        } catch (Exception $e) {
            $response = $api->error($e);
        }

        $httpFoundationFactory = new HttpFoundationFactory();

        return $httpFoundationFactory->createResponse($response);
    }
}
