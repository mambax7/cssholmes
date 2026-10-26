<{assign var=theme_name value=$xoTheme->folderName}>
<!DOCTYPE html>
<html lang="<{$xoops_langcode}>">
<head>
    <meta charset="<{$xoops_charset}>">
    <title><{$xoops_pagetitle}> - <{$xoops_sitename}></title>
    <link rel="stylesheet" href="<{xoImgUrl 'css/style.css'}>">
    <{$xoops_module_header}>
</head>
<body>
<a class="skip-link" href="#content">Skip to content</a>
<{include file="$theme_name/tpl/header.tpl"}>
<main id="content">
    <{$xoops_contents}>
</main>
</body>
</html>
