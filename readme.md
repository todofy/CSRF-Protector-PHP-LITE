CSRF Protector LITE
==========================
[CSRF Protector project](https://github.com/mebjas/CSRF-Protector-PHP) is awesome, it automatically buffers output generated by php, and attaches the javascript code to HTML output. Also attaches hidden tokens in form to support NO-JS versions.

**CSRF Protector LITE** On the other hand, will use the model adopted by `CSRFP` but developer would have to separately add client side javascript code to HTML files and php library will deal with validation of requests. This will remove added overhead of output being buffered for eachr equest and modified.

PROS:
 - Faster, lesser overhead
 - Remove features you don't want :)

CONS:
 - Comparitively less easier to implement


## How to use?

#### library
Include the `php library` at `/libs/csrf/csrfprotector.php` at places where request shall be sent (`submitted`).

```php
include __DIR__ .'/path/to/csrfprotector.php';
csrfprotector::init();
```

#### JS Code
Include the js code at places, from where the reuest shall be sent. Code is available at `js/csrfprotector.js`
```js
<script type="text/javascript" src="/path/to/csrfprotector.js"></script>

```

#### Make Sure
- The name of token is same in both `php library` and `js library`. In the php code its available on `line 14` as
`define("CSRFP_TOKEN","csrfp_token");`. In the JS library its available as `CSRFP_TOKEN` inside the `CSRFP` class.
