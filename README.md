[![Build Status Travis](https://travis-ci.org/madflow/cheesecake.png?branch=master)](https://travis-ci.org/madflow/cheesecake)

#  Cheesecake - the best!

Cheesecake is a project/directory skeleton generator thingy for PHP. It is inspired by
https://github.com/audreyr/cookiecutter.

# Installation

```
    $ composer require madflow/cheesecake
    $ ./vendor/bin/cheesecake
```

# Use it without the cli

```php
    $template = __DIR__ .'/mytemplate';
    $output = __DIR__ .'/myoutput';
    $params = ['project_name' => 'Yeah!'];
    $options = [
        Generator::OPT_OUTPUT => $output,
        Generator::OPT_NO_INTERACTION => true,
    ];
    $o = new Generator($template, $params, $options);
    $o->run();
```

# Development

```
# Clone the repo
git clone https://github.com/madflow/cheesecake.git

# Install dependencies
cd cheesecake
composer install
```

# Examples

##### Create a Silex starter project
```
# Create output directory
mkdir /tmp/silex

# Mmmh  - Cheesecake
./bin/cheesecake -o /tmp/silex examples/silex-starter
```

# Hooks

Put your hooks in ```hooks``` and name them ```pre_gen.php``` or ```post_gen.php```.

# Hacks

+ When processing Twig templates the Mustache engine tries to interpret expressions like ```{{ okay | upper }}``` and will fail with ```Mustache_Exception_UnknownFilterException: Unknown filter: upper```
+  In order to circumvent this, it is possible to define a magic ```filters_ignore``` parameter in your ```cheesecake.json``` file.

```
    {
        "app_name": "twig",
        "filters_ignore": ["upper"]
    }
```
+ ```filters_ignore``` excepts an array of strings which will be translated to dummy filters.

----

+ You can always try to change the delimiter like documented here: https://github.com/bobthecow/mustache.php/wiki/Mustache-Tags#set-delimiter
+ This way it should be possible to circumvent problems with other template engines.

