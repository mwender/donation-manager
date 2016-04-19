
<form action="#" style="float: right;"><input type="text" class="regular-text" placeholder="Enter a zip code" tabindex="9" style="width: 10em;" /><?php submit_button( 'Search', 'secondary', 'search', false, array( 'tabindex' => 10) ); ?></form>
<h3>Donors by Zip Code</h3>
<table id="orphaned-donation-pickup-providers" class="display">
	<colgroup>
		<col style="" />
		<col style="width: 35%" />
		<col style="width: 35%" />
		<col style="width: 10%" />
		<col style="width: 10%" />
		<col style="width: 10%" />
	</colgroup>
	<thead>
		<tr>
			<th></th>
			<th>Name</th>
			<th>Email</th>
			<th>Zip Code</th>
			<th>Priority</th>
			<th>Subscribed</th>
		</tr>
	</thead>
	<tbody></tbody>
</table>
