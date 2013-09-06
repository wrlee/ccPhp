{extends file="usps.inc"}

{block name=head}
{*cc_include files="test.js,test.css"*}
{cc_include files="img/favicon.ico,usps.css"}
<!--style type="text/css">
body { background-color:blue; }
</style-->
{/block}

{block name=body}
<ul>
	<li><a class="menuitem" id="AddressValidate" href="AddressValidate">AddressValidate</a></li>
	<li><a class="menuitem" id="CityStateLookup" href="CityStateLookup">CityStateLookup</a></li>
	<li><a class="menuitem" id="ZipCodeLookup" href="ZipCodeLookup">ZipCodeLookup</a></li>
</ul>
<div id="content"></div>
{/block}

<script>
{*block name=onReady}
$('.menuitem').click(function(evt){
	evt.preventDefault();
	link=this.id;
	console.log(link);
	$('#content').load(link);
	return false;
});
{/block*}
</script>