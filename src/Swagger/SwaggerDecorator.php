<?php
declare(strict_types=1);

namespace App\Swagger;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Extend the auto-generated API documentation with custom endpoints etc,
 * e.g. auth routes and other endpoints without entity.
 * Enabled in the services.yaml
 *
 * @see https://api-platform.com/docs/core/swagger/#overriding-the-openapi-specification
 */
class SwaggerDecorator implements NormalizerInterface
{
    private $decorated;

    public function __construct(NormalizerInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $docs = (array)$this->decorated->normalize($object, $format, $context);

        $docs['paths']['/authentication_token'] = [
            'post' => [
                'tags' => ['Authentication'],
                'operationId' => 'authentication_token',
                'summary' => 'Generate a new JWT to use in the Authorization Header',
                'requestBody' => [
                    'description' => 'The User Credentials',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => [
                                    'email',
                                    'password',
                                ],
                                'properties' => [
                                    'email' => [
                                        'type' => 'string'
                                    ],
                                    'password' => [
                                        'type' => 'string'
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Returns the Authentication Token',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['token'],
                                    'properties' => [
                                        'token' => [
                                            'type' => 'string'
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => [
                        'description' => 'Authentication failed',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['code', 'message'],
                                    'properties' => [
                                        'code' => [
                                            'type' => 'integer'
                                        ],
                                        'message' => [
                                            'type' => 'string'
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return $docs;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->decorated->supportsNormalization($data, $format);
    }
}
