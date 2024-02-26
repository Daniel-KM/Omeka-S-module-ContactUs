Contact Us (module for Omeka S)
===============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Contact Us] is a module for [Omeka S] that allows to add a site page block with
a "Contact us" form. The messages are sent by email to the admin but can be read
directly in the interface too. The form is fully available by api too, so it can
be used by a third party client. It's possible to send a mail to the author of a
resource too.


Installation
------------

See general end user documentation for [installing a module].

The module [Common] must be installed first.

If you use an old theme, you can install [Blocks Disposition] too.

The module uses external libraries, so use the release zip to install it, or
use and init the source.

* From the zip

Download the last release [ContactUs.zip] from the list of releases (the master
does not contain the dependency), and uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `ContactUs`.

Then install it like any other Omeka module and follow the config instructions.


Quick start
-----------

The form can be placed anywhere in the site.

### Config

Fill the options in the main settings, the site settings and the page block.

For subjects and messages, you can use placeholders for customization. They
should be wrapped with `{` and `}`: "from", "email", "name", "site_title",
"site_url", "subject", "message", "ip", "newsletter".
When there is a resource, you can use too "resource_id", "resource_title",
"resource_url", and any property term, like "dcterms:date". Note that the
property should exist in all cases, else it won't be filled.

### Static pages

Create a site page and add the block "Contact us".

The simple antispam is a simple list of questions and answers for the visitor.

The block is themable: copy the file `common/block-layout/contact-us.phtml` in
your theme.

When submitted, the site admin will receive the email, and a confirmation email
will be sent to the visitor.

If you want to use the "Contact us" page in all your sites, you can use the
module [Block Plus], that has a special block to duplicate a page in multiple places.

### Resource pages

The form is displayed automatically on item set, item or media show pages and
item browse page. The settings can be set for each site.

To manage the display more precisely, use the module [Blocks Disposition], or
add the following code in your theme:

```php
// For a single resource.
echo $this->contactUs(['resource' => $resource]);
// For multiple resources.
echo $this->contactUs();
```

The partial is themable: copy the file `common/contact-us.phtml` in your theme.

### Admin interface

The contact us list of message is available in the left sidebar and can be
managed: mark read, set spam, delete.

### Multicheckbox in item/browse or search results

To send contact message with selected items, add a checkbox in the theme
template, for example in "item/browse.phtml" or "search/resource-list.phtml":

```php
<input form="contact-us" class="contact-us-resource" type="checkbox" name="fields[id][]" value="<?= $resource->id() ?>" title="<?= $this->translate('Add to message') ?>"/>
```


Development
-----------

### Testing

Install Omeka with dev dependencies first.

```sh
# From module directory.
composer install
../../vendor/bin/phpunit --testdox --configuration /var/www/html/modules/ContactUs/test/phpunit.xml
```

### Api

Any user can create a message:
```sh
curl -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' -i 'https://example.org/api/contact_messages?key_identity=xxx&key_credential=yyy&pretty_print=1' --data '{"o:email":"alpha@beta.com","o-module-contact:body":"message"}'
```

Or with a file (via a form):
```sh
curl -X POST -H 'Accept: application/json' -i 'https://example.org/api/contact_messages?pretty_print=1' -F 'data={"o:email":"alpha@beta.com","o-module-contact:body":"message"}' -F 'file[0]=@/home/user/my-file.jpeg'
```

Or with a base64-encoded file inside the json payload:
```sh
curl -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' -i 'https://example.org/api/contact_messages?key_identity=xxx&key_credential=yyy&pretty_print=1' --data '{"o:email":"alpha@beta.com","o-module-contact:body":"message","file":[{"name":"filename.txt","base64":"T21la2EgUw=="}]}'
```

Available keys are:
- `o:owner`: generally useless, because the user is already authenticated.
- `o:email`: required for anonymous people.
- `o:name`
- `o-module-contact:subject`: recommended.
- `o-module-contact:body`: required message.
- `o-module-contact:newsletter`: true or false.
- `file`: only one file is currently managed. Useless when the file is sent via
  a posted form.

The owner or the email is useless when the user is already authenticated and is
skipped in that case, except if the user has the right to change the owner of a
message.


TODO
----

- [ ] Fix consent label.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
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

* Copyright Daniel Berthereau, 2018-2024 (see [Daniel-KM] on GitLab)


[Contact Us]: https://gitlab.com/Daniel-KM/Omeka-S-module-ContactUs
[Omeka S]: https://omeka.org/s
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[Blocks Disposition]: https://gitlab.com/Daniel-KM/Omeka-S-module-BlocksDisposition
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Block Plus]: https://gitlab.com/Daniel-KM/Omeka-S-module-BlockPlus
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-ContactUs/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: http://opensource.org/licenses/MIT
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
