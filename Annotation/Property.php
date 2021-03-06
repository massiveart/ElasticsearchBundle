<?php

/*
 * This file is part of the ONGR package.
 *
 * (c) NFQ Technologies UAB <info@nfq.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ONGR\ElasticsearchBundle\Annotation;

use ONGR\ElasticsearchBundle\Mapping\Caser;
use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Required;

/**
 * Annotation used to check mapping type during the parsing process.
 *
 * @Annotation
 * @Target("PROPERTY")
 */
final class Property
{
    /**
     * Field type.
     *
     * @var string
     *
     * @Required
     * @Enum({"string", "boolean", "integer", "float", "long", "short", "byte", "double", "date",
     *        "geo_point", "geo_shape", "ip", "binary", "token_count" })
     */
    public $type;

    /**
     * Name of the type field. Defaults to normalized property name.
     *
     * @var string
     */
    public $name;

    /**
     * In this field you can define options (like analyzers and etc) for specific field types.
     *
     * @var array
     */
    public $options;

    /**
     * {@inheritdoc}
     */
    public function dump(array $exclude = [])
    {
        $array = array_diff_key(
            array_filter(
                get_object_vars($this),
                function ($value) {
                    return $value || is_bool($value);
                }
            ),
            array_flip(array_merge(['name'], $exclude))
        );

        return array_combine(
            array_map(
                function ($key) {
                    return Caser::snake($key);
                },
                array_keys($array)
            ),
            array_values($array)
        );
    }
}
