Contact us (module for Omeka S)
===============================

[Contact us] is a module for [Omeka S] that allows to add a site page block with
a "Contact us" form.


Installation
------------

First, install the two optional modules [Generic] and [Blocks Disposition].

Uncompress files and rename module folder `ContactUs`. Then install it like any
other Omeka module and follow the config instructions.

See general end user documentation for [Installing a module].


Quick start
-----------

The form can be placed anywhere in the site.

# Static pages

Create a site page and add the block "Contact us".

The simple antispam is a simple list of questions and answers for the visitor.

The block is themable: copy the file `common/block-layout/contact-us.phtml` in
your theme.

When submitted, the site admin will receive the email, and a confirmation email
will be sent to the visitor.

If you want to use the "Contact us" page in all your sites, you can use the
module [Next], that has a special block to duplicate a page in multiple places.

# Resource pages

The form is displayed automatically on item set, item or media show pages. The
settings can be set for each site.

To manage the display more precisely, use the module [Blocks Disposition], or
add the following code in your theme:

```php
echo $this->contactUs(['resource' => $resource]);
```

The partial is themable: copy the file `common/helper/contact-us.phtml` in your
theme.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitHub.


License
-------

This module is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Copyright
---------

* Copyright Daniel Berthereau, 2018-2019 (see [Daniel-KM] on GitHub)


[Contact us]: https://github.com/Daniel-KM/Omeka-S-module-ContactUs
[Omeka S]: https://omeka.org/s
[Generic]: https://github.com/Daniel-KM/Omeka-S-module-Generic
[Blocks Disposition]: https://github.com/Daniel-KM/Omeka-S-module-BlocksDisposition
[Installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[Next]: https://github.com/Daniel-KM/Omeka-S-module-Next
[module issues]: https://github.com/Daniel-KM/Omeka-S-module-ContactUs/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: http://http://opensource.org/licenses/MIT
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
