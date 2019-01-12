# Laravel Advanced Search (Laravel 高级搜索)

几乎任何一个系统，都会涉及到搜索，并且可以搜索的项也很可能多。特别是做一些 OA 、ERP 、 CMS 、CRM 等后台系统的时候，各种报表，各种维度的搜索，非常常见。

一个系统会有非常多的列表。每个列表，可能要搜索的字段会有十来个或者更多。搜索的种类有 like 、全等、包含、区间、具体的业务条件等等。

没有经验的程序员可能会在 controller 里面写非常多的判断，非常多的 query 查询。就算是一些有经验的程序员，也很头疼该如何设置写这些逻辑，如何写的优雅。

我做了很多的后台系统，深知其中的痛楚，所以有了这个包，来一刀命中要害，让复杂的搜索简单起来，便于维护，容易理解，同时也变得优雅起来。

## 示例和对比

#### 过去你可能这么写

#### 现在你可能这么写

```php
public function wheres()
{
    return [
        'status',
        // 如果传参 status=1 的话 where status = '1'；
        // 如果 status 前端没有传值，那么不会构造

        'created_at.gt' => $this->fireInput('year', function ($year) {
            return carbon($year.'-01-01 00:00:00');
        }),
        // 如果 year 不传值，什么都不会构造。
        //如果传值 year=2018，那么就会执行闭包方法， 根据闭包结果构造 where year>'2018-01-01 00:00:00'

        'name.like' => $this->appendInput('name', '%'),
        // 如果 name 不传值，什么都不会构造。
        // 如果传值 name=张 ，那么就会构造 where name like '张%'

        'id.in' => $this->getInputArgs('ids', []),
        // 如果 ids 不传值，什么都不会构造，因为默认值为 [] ，构造时会被忽略。
        // 如果传值 ids=[1,3,4] ，那么就会构造 where id in (1,3,4)

        'deleted_at.is_not' => true,
        // 如果判断某个字段是否为 null ，使用 is_not 或者 is ，但是注意对应的值不能为 null ，因为值为 null 时，会被自动跳过

        'age' => [
            'gt' => 12,
            'lt' => 16,
        ],
        // where age > 12 and age < 16

        'height' => [
            'gt'  => '180',
            'lt'  => '160',
            'mix' => 'or',
        ],
        // (age > 180 or age < 160)

        DB::raw('3=4'),
        // where 3=4

        function (Builder $q) {
            $q->where('id', 4);
        },
        // where id=4

        new ModelScope('popular'),
        // 会调用的对应的模型  scopePopular 方法

        new ModelScope('older', 60),
        // 等同于
        function (Builder $q) {
            $q->older(60);
        },

        'id'  => When::make(true)->success('34'),
        // where id = 34
        'url' => When::make(false)->success('http://www.baidu.com')->fail('http://www.google.com'),
        // where url='http://www.google.com'
    ];
}
```

## 安装

`composer require "matrix-lab/laravel-advanced-search"`

## 使用

### 丰富的传参内容

#### fireInput
如果传递的参数内容并不能满足需要，还需要进行一些简单的加工，可以这样做：

```php
return [
	'name.like' => $this->fireInput('name', function ($value) {
         return $value.'%';
    })
];
```

这样就能通过 `?name=张` 来获取所有姓张的员工。

`fireInput` 方法
 第一个参数：前端的传参 key
 第二个参数：处理这个传参的内容
 
`fireInput` 行为
如果不能够获取前端传参，那么直接返回 null ，也就是后续处理中会过滤都这条 where 规则
如果能够获取值，那么将获取值传递到闭包，可以自由的进行处理

#### appendInput

如果仅仅是想在获取的参数值后面添加数据，可以这样来：

```php
return [
	'name.like' => $this->appendInput('name', '%')
];
```

但是你可能会问，为什么不获取数据之后直接添加 `%` ？

```php
return [
	'name.like' => $this->input('name').'%'
];
```

第二种写法是错误的，因为可能返回结果是这样的，当前端没有传参时，结果如下：

```php
return [
	'name.like' => '%'
];
```

这样会按照值为 `%` 进行搜索。

#### when

>当这个人是被禁用户的时候，我们会额外添加一个搜索条件，不让该用户搜索到任何内容

```php
return [
    'id' => $this->when(user()->locked_at, 0),
];
```

这里会根据 `user()->locked_at` 值进行判断，得到的结果如下

```php
// user()->locked_at 值存在
return [
    'id' => 0,
];

// user()->locked_at 值不存在
return [
    'id' => null,
];
```

根据之前约定的，如果 `键值` 为空值（包括空字符串，但不包括 0 ），这个条件就不会生效

`when` 还有以下用法，满足你的各种需求：
```php
'your_field_in_mysql' => $this->when($bool, function() {
    # 这里你可以写你的逻辑，最后返回值即可
    return $this->your_field_in_request/2;
    # 也可以调用一些方法
    # 如果是局部方法，能用 private 就不要用 protected 更不要用 public 定义
    return $this->yourMethodToTransform($value);

    # 同理，返回 null 的时候，这行条件不生效
    return null;
}),
```

#### RAW

原生的 DB::raw 我们也要支持，这个不需要别的，只需要你的语句，只要你会 `sql`，就可以写

```php
return [
    DB::raw('1=0'),
];
```

#### 闭包

如果你的查询*特别*复杂，以上各种形式都满足不了，那么你可以祭出终极大招了

```php
use Illuminate\Database\Eloquent\Builder;

return [
    function (Builder $q) {
        $q->whereHas('role');
    },
];
```

闭包只有一个传参，`$q` 为 `Illuminate\Database\Eloquent\Builder`。看到了这个类，你应该知道如何去使用了吧！

这个就是原生的 `laravel` 查询对象，把所有你需要的查询放里面吧！剩下不多说，自由发挥去吧！

#### and or

查询的时候，经常会有一些逻辑，他们之间可能是 and 或者是 or

```php
return [
    'created_at' => [
        'gt' => '2017-09-01',
        'lt' => '2017-09-30'
    ],
];
```

默认 `created_at` 的 `大于小于` 操作是 `and` 关联，

如果需要 `or` 操作，可以这样写

```php
return [
    'id' => [
        'in'  => [23, 24, 30],
        'lt'  => 25,
        'mix' => 'or'
    ],
];
```


#### 自定义的 laravel 本地作用域

```php
return [
    new ModelScope('listByUser'),
];
```

上面的代码会在执行的时候，会调用模型的 scopeListByUser 方法。

如果需要传参：

```php
return [
    new ModelScope('older', 60),
];
```

上面的代码等同于

```php
function (Builder $q) {
    $q->older(60);
},
```

#### When 对象操作

```php
return [
    'id'  => When::make(true)->success('34'),
    'url' => When::make(false)->success('http://www.baidu.com')->fail('http://www.google.com'),
];
```

执行的结果为
```php
where id = 34
where url='http://www.google.com'
```

## 贡献

有什么新的想法和建议，欢迎提交 [issue](https://github.com/matrix-lab/laravel-advanced-search/issues) 或者 [Pull Requests](https://github.com/matrix-lab/laravel-advanced-search/pulls)。

## 协议

MIT

