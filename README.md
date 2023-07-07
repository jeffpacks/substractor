# About
Substractor is a utility for matching strings against wildcard patterns and for extracting or manipulating substrings according to macro patterns. All pattern formats are easy to understand, suitable for simple string matching,  extraction and manipulation tasks without the need for those wonderful and pesky regular expressions. 

# Dependencies
This library requires at least PHP 7.4.

# Installing

## Composer
Run `composer require jeffpacks/substractor` in your project's root directory.

## Downloading
`git clone https://github.com/jeffpacks/substractor.git` or download from `https://github.com/jeffpacks/substractor/archive/refs/heads/master.zip`.

Add `require('/path/to/substractor/autoload.php')` to your PHP script, and you're good to go. 

# Patterns
Substractor uses only two types of wildcard tokens in its patterns:
- `*` matches any substring of zero or more characters
- `?` matches any single character

You can then build patterns like these and match them against strings:
- `http*://example.test` matches `http://example.test` and `https://example.test`
- `https://example.???` matches `https://example.com` but not `https://example.io`

The wildcard tokens are used for matching, but can also be used for extracting substrings.

A key feature of Substractor's extraction algorithms is that they are word bound. They will look for substrings in "words" (substrings delimited by whitespace characters), rather than across the entire string, giving you `foobar` and not `foobar gone?` when looking for `foo*` in `Where has the foobar gone?`

# Macros
A macro is a named substring or a named set of substrings. By including macro tokens in your patterns, you can extract named substrings. A macro named `foo` looks like this in a pattern: `{foo}`. Extracting the protocol, domain and top-level domain of a URL would require a pattern with macro tokens like this: `{protocol}://{domain}.{top}*`

# The methods

## Substractor::matches()
This method indicates whether a given string matches a pattern that you specify. Here' an example:

```php
<?php
use jeffpacks\substractor\Substractor;

# Checks if a string contains a URL with a subdomain
Substractor::matches(
    'https://example.test/welcome.html https://example.test https://sub.example.test/index.html',
    'https://*.*.*/' # Trailing slash is important, otherwise the dot in welcome.html would count
));
```

## Substractor::subs()
We can extract all substrings that fully match a given pattern with this method. Say you want to extract all e-mail addresses from a string. It'll be easy – like breaking a toothpick:
```php
$addresses = Substractor::subs('Please contact jeffpacks@varen.no or jfvaren@gmail.com for more info', '*@*.*');
```

## Substractor::macros()
This method lets you extract named substrings. If you want to parse a route URL, like in Laravel, you would do it like so:
```php
# Returns ['userId' => '42', 'settingId' => '3221']
$segments = Substractor::macros('/user/42/settings/3221', '/user/{userId}/settings/{settingId}');
```

You can of course specify multiple patterns:
```php
# Returns ['userId' => '42', 'settingId' => '3221']
$segments = Substractor::macros(
    '/user/42/settings/3221',
    [
        '/user/{userId}/settings/{settingId}'
        '/users/{userId}/settings/{settingId}'
    ]
);
```

There may be cases where you want to use one macro pattern over another based on the general pattern of the string you're searching. We achieve this by keying each macro pattern with a "key pattern" which the entire string must match in order for the macro pattern to be used. Here's an example:
```php
$patterns = [
    '*.*.*-alpha.*' => '{major}.{minor}.{patch}-*.{alpha}',
    '*.*.*-beta.*' => '{major}.{minor}.{patch}-*.{beta}'
];

# Returns ['major' => '1', 'minor' => '2', 'patch' => '3', 'alpha' => '1']
$segments = Substractor::macros('1.2.3-alpha.1', $patterns);

# Returns ['major' => '1', 'minor' => '2', 'patch' => '3', 'beta' => '1']
$segments = Substractor::macros('1.2.3-beta.1', $patterns);

# Returns [] because the string does not contain 'alpha' or 'beta'
$segments = Substractor::macros('1.2.3', $patterns);
```

Note that this method will only return one substring per macro token. If you have a string with multiple matching substrings, only the first matches will be returned. Example:
```php
# Returns ['name' => 'sales', 'domain' => 'example.test']
$segments = Substractor::macros('Please contact sales@example.test or contact@example.test for more info', '{name}@{domain}');
```

If you need to extract all matching substrings, use the `Substractor::macrosAll()` method.

## Substractor::macrosAll()
This method will return named sets of substrings, as opposed to `Substractor::macros()` which only returns single named substrings. Here's an example:

```php
# Returns ['name' => ['sales', 'contact'], 'domain' => ['example.test', 'example.test']]
$segments = Substractor::macrosAll('Please contact sales@example.test or contact@example.test for more info', '{name}@{domain}');
```

## Substractor::pluck()
If you only want the first matching substring, this method is for your convenience. It will call `Substractor::macros()` for you and return the substring of a macro you specify. Example:
```php
# Returns 'sales'
$name = Substractor::pluck('Please contact sales@example.test or contact@example.test for more info', '{name}@{domain}', 'name');
```

## Substractor::pluckAll()
If you want to pluck all substrings matching a given macro token, use this method. It will call `Substractor::macrosAll()` for you and return the substrings of a macro you specify. Example:
```php
# Returns ['sales', 'contact']
$name = Substractor::pluckAll('Please contact sales@example.test or contact@example.test for more info', '{name}@{domain}', 'name');
```

## Substractor::replace()
If you want to replace or manipulate a specific substring within some other string, you can use the `Substractor::replace()` method. By specifying a macro pattern, you can tell it which of those macros you want to replace or manipulate using method chaining. Here's an example where we encrypt and decrypt the password segment of a URL:

```php
$secret = 'OQvBIbYzkz';
$url = 'https://john:password123@example.test';

$encryptedUrl = (string) Substractor::replace($url, '*://*:{pass}@*')->pass(fn($password) => openssl_encrypt($password, 'AES-128-CTR', $secret, 0, '1234567891011121'));

$decryptedUrl = (string) Substractor::replace($encryptedUrl, '*://*:{pass}@*')->pass(fn($password) => openssl_decrypt($password, 'AES-128-CTR', $secret, 0, '1234567891011121'));

echo "Encrypted: $encryptedUrl\n";
echo "Decrypted: $decryptedUrl\n";

# Outputs:
# Encrypted: https://john:emIdK6ITultd0S0=@example.test
# Decrypted: https://john:password123@example.test
```

The object returned from `Substrator::replace()` have (magic) methods that correspond to the macro tokens you specify in your macro pattern. This way, you can specifically target the substrings you want to replace, even if some of them are identical. Each method will return the same object, so you can chain your calls:

```php
$url = 'http://john:https@example.test:80';

echo (string) Substractor::replace($url, '{protocol}://{user}:{pass}@{host}:{port}')->protocol('https')->port('22');

# Outputs:
# https://john:password123@example.test:22
```

## Redaction
Sometimes characters get in the way of what you're trying to match and/or extract. Consider a Markdown document containing links, such as this one: `You can [e-mail me](mailto:jeffpacks@varen.no) or reach me on [Github](https://github.com/jeffpacks)`. Attempting to extract the substring `e-mail me` using the pattern `[*]` would yield no result because the single `*` wildcard tries to match a single word, but there are two words between these brackets. This is a case where we can use the `redaction` parameter that all the Substractor methods accept. There are 3 types of redactions:
1. Pre-redaction: A given character/string will be removed before the matching takes place, but is left intact in the returned substrings
2. Post-redaction: A given character/string is left untouched prior to matching, but is removed from the returned substrings
3. Full redaction: A given character/string is removed prior to matching and is also removed from the returned substrings (a combination of pre- and post-redaction).

We can use pre-redaction to remove the space character between the two words prior to matching, but allow it to remain untouched in the returned substring:
```php
$markdown = 'You can [e-mail me](mailto:jeffpacks@varen.no) or reach me on [Github](https://github.com/jeffpacks)';
$result = Substractor::subs($markdown, '[*]', ' ');
```
The above will give the substring `[e-mail me]`, including the brackets. If we don't want the brackets, we will have to do a post-redaction on those:
```php
$markdown = 'You can [e-mail me](mailto:jeffpacks@varen.no) or reach me on [Github](https://github.com/jeffpacks)';
$result = Substractor::subs($markdown, '[*]', [
    ' ', # pre-redact the space
    '[' => false, # false indicates post-redaction 
    ']' => false
]);
```
This gives us the `e-mail me` substring only, as the brackets have been post-redacted.

Full redaction is a less common use-case, but is indicated by specifying a boolean `true` as the value of the redaction array.

# Authors
* [Johan Fredrik Varen](mailto:jeffpacks@varen.no)

# License
MIT License
