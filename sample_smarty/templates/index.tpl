{extends file="usps.inc"}

{block name=head}
{*cc_include files="test.js,test.css"*}
{cc_include files="img/favicon.ico,usps.css"}
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
{block name=onReady}
$('.menuitem').click(function (evt) {
    evt.preventDefault();
    var link = this.id;
    console.log(link); 
    $('#content').load(link);
/*	$.ajax({
            type: 'POST',
			url: link,
    //data: body,
            dataType: 'html',
            success: function (data, textStatus, jqXHR) {
                console.log(data);
			    $('#content')[0].innerHTML = data;
            },
            error: function (xhr, textStatus, errThrown) {
                console.log(xhr);
                console.log(textStatus);
                console.log(errThrown);
                $('#message').html(xhr.responseText);
            }
    });
*/	return false;
});
{/block}
</script>