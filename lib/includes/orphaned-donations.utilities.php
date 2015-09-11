<h3>Test a Pickup Code</h3>
<form id="form-test-pcode">
<table>
	<tr>
		<td><input name="test-pcode" id="test-pcode" value="" placeholder="test a pickup code" /></td>
		<td><select name="test-radius" id="test-radius">
	<option value="20">20 miles</option>
	<option value="25">25 miles</option>
	<option value="30">30 miles</option>
	<option value="35">35 miles</option>
	<option value="40">40 miles</option>
	<option value="50">50 miles</option>
</select></td>
		<td><button class="button" id="test-pcode-button" type="submit">Test</button></form></td>
	</tr>
</table>
<div class="output" id="output-test-pcode"></div>
<hr/>

<h3>Search/Replace an Email</h3>
<form id="form-search-replace-email">
<table>
	<tr>
		<td><input name="search-email" id="search-email" value="" size="60" placeholder="search" /></td>
		<td><input name="replace-email" id="replace-email" value="" size="60" placeholder="replace" /></td>
		<td><button class="button" id="search-replace-email" type="submit">Search and/or Replace</button></td>
	</tr>
	<tr>
		<td colspan="3">NOTE: Leave <code>replace</code> value blank for <em>search only</em>.</td>
	</tr>
</table>
</form>
<div class="output" id="output-search-replace"></div>
<hr/>

<h3>Unsubscribe an Email</h3>
<form action="" id="form-unsubscribe">
	<table>
		<tr>
			<td><input name="unsubscribe-email" id="unsubscribe-email" value="" size="60" placeholder="enter email to unsubscribe" /></td>
			<td><button class="button" id="unsubscribe-email" type="submit">Unsubscribe</button></td>
		</tr>
	</table>
</form>
<div class="output" id="output-unsubscribe"></div>