	<section id="{if isset($user.logged) && $user.logged}content_logged{else}content{/if}">
      <div class="container-fluid">
 		<div class="content-inner{if isset($content_inner_class) && $content_inner_class} {$content_inner_class}{/if}">
