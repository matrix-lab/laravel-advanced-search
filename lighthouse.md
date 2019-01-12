# laravel-advanced-search 支持 lighthouse 

## 安装

*仅支持 Lighthouse 3.0 及以上版本，因为 3.0 开始支持扩展开发，并且有不少新特性，非常值得升级*

加入该依赖包后，在 config/lighthouse.php 配置文件中，找到 namespaces 配置，添加一行 `'directives' => ['MatrixLab\\LaravelAdvancedSearch\\Lighthouse\\Directives']` 即可

添加 Model 相关的依赖 trait，如下

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MatrixLab\LaravelAdvancedSearch\AdvancedSearchTrait;
use MatrixLab\LaravelAdvancedSearch\WithAndSelectForGraphQLGeneratorTrait;

class BaseModel extends Model
{
    use AdvancedSearchTrait, WithAndSelectForGraphQLGeneratorTrait;

```

- WithAndSelectForGraphQLGeneratorTrait 是针对 GraphQL 进行性能优化的 trait ，主要作用是根据前端请求的结构内容，最小化查询的 select 内容，以及自动加载关联模型
- AdvancedSearchTrait 是 getlist 的基础方法 trait 

## 使用

安装后就可以开心的使用 getlist 方法了

### 定义

例如：

```graphql
type Query {
    users(paginator: PaginatorInput!): [User!]! @getlist
}
```

GraphQL 的 schema 定义等同于

```graphql
type Query {
    users(paginator: PaginatorInput!): UserPaginator
}

type UserPaginator {
    items: [User!]!
    cursor: PaginationCursor!
}
```

以及一个全局的分页输入定义

```graphql
input PaginatorInput {
    page: Int
    limit: Int
    sort: String
    sorts: [String]
}
```

### 内置参数用法

参数通过接口传入，city_id 为 1

```graphql
type Query {
    users(paginator: PaginatorInput!, city_id: Int): [User!]! @getlist
}
```

那么查询时则会添加 where `city_id`=1

同时等同于

```graphql
type Query {
    users(paginator: PaginatorInput!, more: UsersInput): [User!]! @getlist
}

# UsersInput 需要自己额外定义一下
input UsersInput{
    city_id: int
}
```

如果参数不需要通过接口，可以这样写：

```graphql
type Query {
    users(paginator: PaginatorInput!): [User!]! @getlist(args: {city_id: 1})
}
```

### 自定义解析

```graphql
type Query {
    users(paginator: PaginatorInput!): [User!]! @getlist(resolver: "App\\GraphQL\\Queries\\Users@resolve")
}
```

那么可以新建一个处理类，会对应调用定义的 `resolve` 方法