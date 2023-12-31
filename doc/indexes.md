Index configuration
==================

Custom Property Paths
---------------------

Custom property paths can be used for data retrieval from the underlying model.

```yaml
fos_elastica:
    indexes:
        user:
            properties:
                username:
                    property_path: indexableUsername
                firstName:
                    property_path: names[first]
```

This feature uses the Symfony PropertyAccessor component and supports all features
that the component supports.

The above example would retrieve an indexed field `username` from the property
`User->indexableUsername`, and the indexed field `firstName` would be populated from a
key `first` from an array on `User->names`.

Setting the property path to `false` will disable transformation of that value. In this
case the mapping will be created but no value will be populated while indexing. You can
populate this value by listening to the `FOS\ElasticaBundle\Event\PostTransformEvent` event emitted by this bundle.
See [cookbook/custom-properties.md](cookbook/custom-properties.md) for more information
about this event.

Handling missing results with FOSElasticaBundle
-----------------------------------------------

By default, FOSElasticaBundle will throw an exception if the results returned from
Elasticsearch are different from the results it finds from the chosen persistence
provider. This may pose problems for a large index where updates do not occur instantly
or another process has removed the results from your persistence provider without
updating Elasticsearch.

The error you're likely to see is something like:
'Cannot find corresponding Doctrine objects for all Elastica results.'

To solve this issue, each index can be configured to ignore the missing results:

```yaml
fos_elastica:
    indexes:
        user:
            persistence:
                elastica_to_model_transformer:
                    ignore_missing: true
```

Dynamic templates
-----------------

Dynamic templates allow to define mapping templates that will be
applied when dynamic introduction of fields / objects happens.

[Documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/dynamic-templates.html)

```yaml
fos_elastica:
    indexes:
        user:
            dynamic_templates:
                - my_template_1:
                    match: apples_*
                    mapping:
                        type: float
                - my_template_2:
                    match: *
                    match_mapping_type: text
                    mapping:
                        type: keyword
            properties:
                username: { type: text }
```

Nested objects in FOSElasticaBundle
-----------------------------------

Note that object can autodetect properties

```yaml
fos_elastica:
    indexes:
        post:
            properties:
                date: { boost: 5 }
                title: { boost: 3 }
                content: ~
                comments:
                    type: "nested"
                    properties:
                        date: { boost: 5 }
                        content: ~
                user:
                    type: "object"
                approver:
                    type: "object"
                    properties:
                        date: { boost: 5 }
```

Date format example
-------------------

If you want to specify a [date format](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-date-format.html):

```yaml
fos_elastica:
    indexes:
        user:
            properties:
                username: { type: text }
                lastlogin: { type: date, format: basic_date_time }
                birthday: { type: date, format: "yyyy-MM-dd" }
```


Disable dynamic mapping example
-------------------

If you want to specify manually the dynamic capabilities of Elasticsearch mapping, you can use 
the [dynamic](https://www.elastic.co/guide/en/elasticsearch/reference/current/dynamic.html) option:

```yaml
fos_elastica:
    indexes:
        user:
            dynamic: strict
            properties:
                username: { type: text }
                addresses: { type: object, dynamic: true }
```

With this example, Elasticsearch is going to throw exceptions if you try to index a not mapped field, except in `addresses`.

Custom settings
---------------

Any setting can be specified when declaring an index. For example, to enable a custom
analyzer, you could write:

```yaml
fos_elastica:
    indexes:
        blog:
            settings:
                index:
                    analysis:
                        analyzer:
                            my_analyzer:
                                type: custom
                                tokenizer: lowercase
                                filter   : [my_ngram]
                        filter:
                            my_ngram:
                                type: "nGram"
                                min_gram: 3
                                max_gram: 5
            properties:
                title: { boost: 8, analyzer: my_analyzer }
```

Testing if an object should be indexed
--------------------------------------

FOSElasticaBundle can be configured to automatically index changes made for
different kinds of objects if your persistence backend supports these methods,
but in some cases you might want to run an external service or call a property
on the object to see if it should be indexed.

A property, `indexable_callback` is provided under the index configuration that
lets you configure this behaviour which will apply for any automated watching
for changes and for a repopulation of an index.

In the example below, we're checking the enabled property on the user to only
index enabled users.

```yaml
fos_elastica:
    indexes:
        user:
            indexable_callback: 'enabled'
```

The callback option supports multiple approaches:

* A method on the object itself provided as a string. `enabled` will call
  `Object->enabled()`. Note that this does not support chaining methods with dot notation
  like property paths. To achieve something similar use the ExpressionLanguage option
  below.
* An array of a service id and a method which will be called with the object as the first
  and only argument. `[ @my_custom_service, 'userIndexable' ]` will call the userIndexable
  method on a service defined as my_custom_service.
* An array of a class and a static method to call on that class which will be called with
  the object as the only argument. `[ 'Acme\DemoBundle\IndexableChecker', 'isIndexable' ]`
  will call Acme\DemoBundle\IndexableChecker::isIndexable($object)
* A single element array with a service id can be used if the service has an __invoke
  method. Such an invoke method must accept a single parameter for the object to be indexed.
  `[ @my_custom_invokable_service ]`
* If you have the ExpressionLanguage component installed, A valid ExpressionLanguage
  expression provided as a string. The object being indexed will be supplied as `object`
  in the expression. `object.isEnabled() or object.shouldBeIndexedAnyway()`. For more
  information on the ExpressionLanguage component and its capabilities see its
  [documentation](http://symfony.com/doc/current/components/expression_language/index.html)

In all cases, the callback should return a boolean, with `true` indicating it will be
indexed, and `false` indicating the object should not be indexed, or should be removed
from the index if we are running an update.

Provider Configuration
----------------------

### Specifying a custom query builder for populating indexes

When populating an index, it may be required to use a different query builder method
to define which entities should be queried.

```yaml
fos_elastica:
    indexes:
        user:
            persistence:
                provider:
                    query_builder_method: createIsActiveQueryBuilder
```

### Populating batch size

By default, ElasticaBundle will index documents by packets of 100.
You can change this value in the provider configuration.

```yaml
fos_elastica:
    indexes:
        user:
            persistence:
                provider:
                    batch_size: 10
```

### Changing the document identifier

By default, ElasticaBundle will use the `id` field of your entities as
the Elasticsearch document identifier. You can change this value in the
persistence configuration.

```yaml
fos_elastica:
    indexes:
        user:
            persistence:
                identifier: searchId
```

### Turning on the persistence backend logger in production

FOSElasticaBundle will turn off your persistence backend's logging configuration by default
when Symfony is not in debug mode. You can force FOSElasticaBundle to always disable
logging by setting debug_logging to false, to leave logging alone by setting it to true,
or leave it set to its default value which will mirror %kernel.debug%.

```yaml
fos_elastica:
    indexes:
        user:
            persistence:
                provider:
                    debug_logging: false
```

Listener Configuration
----------------------

### Realtime, selective index update

If you use the Doctrine integration, you can let ElasticaBundle update the indexes automatically
when an object is added, updated or removed. It uses Doctrine lifecycle events.
Declare that you want to update the index in real time:

```yaml
fos_elastica:
    indexes:
        user:
            persistence:
                driver: orm #the driver can be orm, mongodb or phpcr
                model: Application\UserBundle\Entity\User
                listener: ~ # by default, listens to "insert", "update" and "delete"
```

Now the index is automatically updated each time the state of the bound Doctrine repository changes.
No need to repopulate the whole "user" index when a new `User` is created.

You can also choose to only listen for some of the events:

```yaml
fos_elastica:
    indexes:
        user:
            persistence:
                listener:
                    insert: true
                    update: false
                    delete: true
```

### Asynchronous index update

You can also tell ElasticaBundle to update the indexes after Symfony response has returned.
This is useful when you want your responses to return quickly and not be slowed down by round
trips to your Elasticsearch instance. All updates to Elasticsearch will be batched up and
only fire after the `kernel.terminate` and `console.terminate` events.

```yaml
fos_elastica:
    indexes:
        user:
            persistence:
                listener:
                    defer: true
```

Logging Errors
--------------

By default FOSElasticaBundle will not catch errors thrown by Elastica/Elasticsearch.
Configure a logger per listener if you would rather catch and log these.

```yaml
fos_elastica:
    indexes:
        user:
            persistence:
                listener:
                    logger: true
```

Specifying `true` will use the default Elastica logger.  Alternatively define your own
logger service id.
