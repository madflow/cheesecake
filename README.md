[![Build Status Travis](https://travis-ci.org/madflow/cheesecake.png?branch=master)](https://travis-ci.org/madflow/cheesecake) [![Build Status Appveyor](https://ci.appveyor.com/api/projects/status/07ik73aibio5w4p7?svg=true)](https://ci.appveyor.com/project/madflow/cheesecake)

#  Cheesecake - the best!

Cheesecake is a project/directory skeleton generator thingy for PHP. It is inspired by
https://github.com/audreyr/cookiecutter.

# Get started

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
