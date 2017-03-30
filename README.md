# Codeigniter Smarty Parser
Integrate Smarty into your Codeigniter applications.

## How install?

composer require muraveiko/codeigniter-smarty-parser

## How to use it?
Copy files from vendor\muraveiko\codeigniter-smarty-parser\dist directory in application folder.
Edit your autoload.php file in the config folder, and add 'parser' to your list of autoloaded libraries. Instead of using $this->load->view() you now use $this->parser->parse() instead. That's it.

## Supported versions of Codeigniter
CI Smarty has been tested and working on the latest version of Codeigniter (3.1.x). 

## Theming support
CI Smarty comes with complimentary functionality to add theming support in your Codeigniter applications. Simply create a themes directory in the root folder of your app and then inside of that folders of themes. If you're not using themes, then don't add anything and CI Smarty will work fine without them. It's a good idea if you're building a web app to have a default theme in application/themes/themename and then allow themes in a different directory to override your default theme files.

## Is based on CI Smarty

by   Dwayne Charrington
http://ilikekillnerds.com
