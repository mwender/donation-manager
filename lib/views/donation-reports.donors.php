
<form id="form-donor-report" action="#" style="float: right;"><input type="text" class="regular-text" placeholder="Enter a zip code" tabindex="9" style="width: 10em;" id="query-zip" /><?php submit_button( 'Search', 'secondary', 'search', false, array( 'tabindex' => 10) ); ?></form>
<pre id="response-body"></pre>
<h3>Donors by Zip Code</h3>
<table id="table-donor-report" class="display">
	<colgroup>
		<col style="" />
		<col style="width: 10%" />
		<col style="width: 15%" />
		<col style="width: 20%" />
		<col style="width: 10%" />
		<col style="width: 45%" />
	</colgroup>
	<thead>
		<tr>
			<th>ID</th>
			<th>Date</th>
			<th>Name</th>
			<th>Email</th>
			<th>Zip Code</th>
			<th></th>
		</tr>
	</thead>
	<tbody></tbody>
</table>
