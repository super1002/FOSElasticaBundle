Provider
========

The provider concept abstracts the source objects and ability to fetch them and iterate over them. 
It is useful in combination with persister to populate the index with the data.

_**Note:** The doc describes v5 api which is disabled by default._

Here's example on how to configure Doctrine ORM provider

```yaml
# app/config/config.yml

parameters:
    fos_elastica.use_v5_api: true

fos_elastica:
    indexes:
        theIndexName:
            types:
                theTypeName:
                    persistence:
                        driver: orm
                        model: Application\UserBundle\Entity\User
                        provider: 
                            pager_provider: true
```

There are other providers Doctrine MongoDB, Doctrine PHPCR, Propel1 available.


Provider options
----------------

[Back to index](../index.md)
