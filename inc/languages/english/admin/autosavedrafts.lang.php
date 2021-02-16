<?php

// Installation
$l['autosavedrafts'] = "Drafts AutoSave";
$l['autosavedrafts_pluginlibrary_missing'] = "<a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing. Please install it before doing anything else with Drafts AutoSave.";

// Settings
$l['setting_group_autosavedrafts'] = "Drafts AutoSave settings";
$l['setting_group_autosavedrafts_desc'] = "Here you can manage options regarding the automatic save of messages as drafts in your Forum.";
$l['setting_autosavedrafts_enable'] = "Master switch";
$l['setting_autosavedrafts_enable_desc'] = "Do you want to enable Drafts AutoSave? Turn it to off if you want to disable the plugin.";
$l['setting_autosavedrafts_interval'] = "Saving interval";
$l['setting_autosavedrafts_interval_desc'] = "Specify an interval in which the message will be saved as draft automatically, in seconds. Default to 60 (1 minute). <b>It is highly recommended to NOT set this interval below 10 seconds.</b> Remember that if you don't use the browser LocalStorage as your saving engine, this will execute at least one query this often. If you encounter server downtimes, set this to higher values.";
$l['setting_autosavedrafts_messages'] = "Messages engine";
$l['setting_autosavedrafts_messages_desc'] = "Choose to display a message as a popup, and if so choose using jGrowl (MyBB 1.8 default) or use the awesomeness of Humane.js. If you are on MyBB 1.6, jGrowl is not available.";
$l['setting_autosavedrafts_engine'] = "Saving engine";
$l['setting_autosavedrafts_engine_desc'] = "Pick up your preferred saving engine. PluginLibrary is the fastest method and does not query the database, but its cache may vanish at any time (eg.: server's failure). Database is the most reliable method but too many queries might slow down your server.";
$l['setting_autosavedrafts_browser'] = "Use browser LocalStorage as saving engine";
$l['setting_autosavedrafts_browser_desc'] = "Enable this option to save messages in the user's browser. This is a client-side solution - enabled by default - and does not use any server-side resources, but if an user opens your site in another browser, he won't be able to load any saved drafts. If a browser does not support LocalStorage, the option above will determine the fallback used: for example, if an user is using Internet Explorer 8 he won't be able to store messages in the browser and the database or PluginLibrary will be used instead.";
$l['setting_autosavedrafts_scroll'] = "Scroll to textarea or editor";
$l['setting_autosavedrafts_scroll_desc'] = "Enable this option to automatically (and gently) scroll to the textarea or the editor upon loading the saved drafts.";
$l['setting_autosavedrafts_direct_load'] = "Direct drafts load";
$l['setting_autosavedrafts_direct_load_desc'] = "Enable this option to load any saved drafts (if available) immediately, without asking users to to so, when they open a thread or post a new thread in a forum.";