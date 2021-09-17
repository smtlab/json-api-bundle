# Rapid API generator for Symfony

Use this symfony bundle to quickly generate APIs based on JSON:API with 0 configuration.

# Prerequisites

Make sure there is no route defined in path `/api`

# Accessing API

* /api/v1/{EntityName}
* {EntityName} is plural camel case EntityName.
* Ex: APIs for `Post` can be accessed at `/api/v1/Posts`  

# Roadmap

* Make api url configuration
* Allow to enable API only for specific Entities
* Enable entity fields specific configuration