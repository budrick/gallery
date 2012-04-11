# Simple image browser

**WARNING:** This is very much in the early stages of development. Things may change radically, or break compatibility, or just never finish.

## What it is

This is intended to be a fairly straightforward replacement for packages like Single-File PHP Gallery. There are pros and cons to my approach.

### Cons:

* (At least for now) nowhere near single-file. Hopefully to be packaged into a phar sometime soon, though.
* Totally not finished yet.
* Not all cons listed in the cons list

### Pros:

* Because output is rendered with Twig, you have a huge amount of control over how the gallery looks
* Super-easy configuration with an (optional) ini file
* One installation should work for multiple folders
* Uses Silex and Symfony's HttpCache to limit resource use.

