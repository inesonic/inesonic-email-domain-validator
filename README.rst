===============================
inesonic-email-domain-validator
===============================
You can use this small plugin to force NinjaForms to validate that a supplied
email domain if associated with an accredited academic institution.

To use, simply copy this entire directory into your WordPress plugins directory
and then activate the plugin from the WordPress admin panel.

Email addresses with the NinjaForms field name "user_meta_email_academic" will
be managed by this plug.

This plugin relies on the JetBrains SWOT database.  That database can be found
under the "fields/domain" directory.  You should consider periodically updating
that database.  The format of the database can be found in the "readme.txt"
file located in the "fields" directory.

You can add additional email domains by adding directories and text fields using
the same format into the "fields/extra" directory.

Many thanks to JetBrains for supplying the database used (under MIT license
terms).  That database can be found at https://github.com/JetBrains/swot.  Also
thank you to the original authors.  The original version can be found at
https://github.com/leereilly/swot.
