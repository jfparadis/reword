#Sk Reword - ExpressionEngine Plugin

Version: 1.0.0
Jean-Francois Paradis - http://github.com/skaimauve
Copyright (c) 2012 Jean-Francois Paradis
MIT License - please see LICENSE file included with this distribution
http://github.com/skaimauve/reword

##Introduction

This plugin extends on similar functionality provided by the function translate() or __() in Wordpress which translates a given string using a dictionary. 

This implementation can also handle placeholders (eg: Hello %s) and can translate dates formats (eg: Today is %d).

The language of the user is detected using the browser settings and stored in a cookie. The cooke can be altered by providing a 'lang' parameter in the url (eg: http://example.com?lang=fr).

The list of supported languages is defined in languages.php, which allows to convert between language codes (sent by the browser 'Accept Language' header) and language strings used by Code Igniter.

The dictionaries are located in the 'language_path' folder (default /themes) defined in the configuration, in the given sites/language subfolder. The system also supports multiple language sets (default 'all').

example (one file for all):
/var/www/themes/languages/default_site/french/lang.all.php

example (one file for set='front', one for set='profile', the rest goes in 'all'): 
/var/www/themes/languages/default_site/french/lang.front.php
/var/www/themes/languages/default_site/french/lang.profile.php
/var/www/themes/languages/default_site/french/lang.all.php

The language file must define a $lang associative array as follows:

example: 

<?php
$lang = array(
'Hello' => 'Bonjour',
'Hello %s' => 'Bonjour %s',
'%s search results for "%s"' => '%s résultats pour "%s"',
'%M %d, %Y' => '%d %M %Y',
'Posted %d' => "Publié le %d"
);

If navigating to example.com/?lang=fr:

{exp:sk_reword text='Hello'}<br />
{exp:sk_reword text='Hello %s' string='Mary'}<br />
{exp:sk_reword text='%s search results for "%s"' string='25|Test'}<br />
{exp:sk_reword text='Posted %d' format='%M %d, %Y' date='2010-01-01'}

Renders as:

Bonjour
Bonjour Mary
25 résultats pour "Test"
Publié le 07 Sep 2012

##Setup

Download the "sk_reword" folder and upload it to the third party directory of your ExpressionEngine folder.

##Usage:

### Basic Translation

{exp:sk_reword text=''}

Replaces $text with its translated equivalence in the dictionary using the language of the user. If there is no translation, the original text is returned.

example: {exp:sk_reword text='Hello'}  

You can also specify a language set.

example: {exp:sk_reword set='profile' text='My Profile'}  

### String replacement

{exp:sk_reword text='' string=''}

Translates $text as above, then replaces %s in $text with the content of $string. Can also replace multiple occurrences of %s when multiple values are provided in $string and separated by '|'. 

example: {exp:sk_reword text='Hello %s' string='{username}'}  
example: {exp:sk_reword text='%s search results for "%s"' string='{exp:search:total_results}|{exp:search:keywords}'}  

### Date format and replacement

{exp:sk_reword text='' format='' date=''}

Translates $text as above, then translates $format using the same process, and then replaces %d in $text with $date (default current date) using the given $format. This function is used to support language-specific date formats. If $text is not provided, the date is returned with the correct format.

example: {exp:sk_reword text='Posted %d' format='%M %d, %Y' date='{entry_date}'}
example: {exp:sk_reword format='%M %d, %Y'}

##Changelog

1.0.0 - Initial plugin