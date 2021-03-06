{include file="documentHeader"}
<head>
	<title>{lang}cp.domain.edit{/lang} - {PAGE_TITLE}</title>

	{include file='headInclude' sandbox=false}

	<script type="text/javascript" src="{@RELATIVE_WCF_DIR}js/Suggestion.class.js"></script>
	<script type="text/javascript" src="{@RELATIVE_WCF_DIR}js/TabMenu.class.js"></script>
	<script src="{@RELATIVE_WCF_DIR}js/Calendar.class.js" type="text/javascript"></script>
	<script type="text/javascript">
		//<![CDATA[
		var tabMenu = new TabMenu();
		{if $options|count}onloadEvents.push(function() { tabMenu.showSubTabMenu('{@$options.0.categoryName}') });{/if}
		//]]>
	</script>
</head>
<body>
{include file='header' sandbox=false}

<div id="main">

	<ul class="breadCrumbs">
		<li><a href="index.php{@SID_ARG_1ST}"><img alt="" src="{icon}wcpS.png{/icon}"> <span>{lang}cp.header.menu.start{/lang}</span></a> &raquo;</li>
		<li><a href="index.php?page=DomainList{@SID_ARG_2ND}"><img alt="" src="{icon}domainS.png{/icon}"> <span>{lang}cp.header.menu.domain{/lang}</span></a> &raquo;</li>
	</ul>

	<div class="mainHeadline">
		<img src="{icon}domain{@$action|ucfirst}L.png{/icon}" alt="" />
		<div class="headlineContainer">
			<h2>{lang}cp.domain.edit{/lang}</h2>
		</div>
	</div>

	{if $errorField}
		<p class="error">{lang}wcf.global.form.error{/lang}</p>
	{/if}

	{if $success|isset}
		<p class="success">{lang}cp.domain.edit.success{/lang}</p>
	{/if}

	<form method="post" action="index.php?form=Domain{@$action|ucfirst}">
		<div class="border tabMenuContent">
			<div class="container-1">
				<fieldset>
					<legend>{lang}cp.domain.data{/lang}</legend>

					<div class="formElement{if $errorType.domainname|isset} formError{/if}" id="domainnameDiv">
						<div class="formFieldLabel">
							<label for="domainname">{lang}cp.domain.domainname{/lang}</label>
						</div>
						<div class="formField">
							{$domainname}
						</div>
					</div>

					{if $additionalFields|isset}{@$additionalFields}{/if}
				</fieldset>

				{if $additionalFieldSets|isset}{@$additionalFieldSets}{/if}

				{if $options|count || $additionalTabs|isset}
				<div class="tabMenu">
					<ul>
						{foreach from=$options item=categoryLevel1}
							<li id="{@$categoryLevel1.categoryName}"><a onclick="tabMenu.showSubTabMenu('{@$categoryLevel1.categoryName}');"><span>{lang}cp.domain.option.category.{@$categoryLevel1.categoryName}{/lang}</span></a></li>
						{/foreach}

						{if $additionalTabs|isset}{@$additionalTabs}{/if}
					</ul>
				</div>
				<div class="subTabMenu">
					<div class="containerHead"><div> </div></div>
				</div>

				{foreach from=$options item=categoryLevel1}
					<div class="border tabMenuContent hidden" id="{@$categoryLevel1.categoryName}-content">
						<div class="container-1">
							<h3 class="subHeadline">{lang}cp.domain.option.category.{@$categoryLevel1.categoryName}{/lang}</h3>

							{foreach from=$categoryLevel1.categories item=categoryLevel2}
								<fieldset>
									<legend>{lang}cp.domain.option.category.{@$categoryLevel2.categoryName}{/lang}</legend>

									{include file='domainOptionFieldList' options=$categoryLevel2.options}
								</fieldset>
							{/foreach}
						</div>
					</div>
				{/foreach}
			{/if}
			</div>
		</div>

		<div class="formSubmit">
			{@SID_INPUT_TAG}
			{@SECURITY_TOKEN_INPUT_TAG}
	 		<input type="hidden" name="action" value="{@$action}" />
	 		{if $domainID|isset}<input type="hidden" name="domainID" value="{@$domainID}" />{/if}
	 		<input type="submit" accesskey="s" value="{lang}wcf.global.button.submit{/lang}" />
			<input type="reset" accesskey="r" value="{lang}wcf.global.button.reset{/lang}" />
	 	</div>
	</form>

</div>

{include file='footer'}