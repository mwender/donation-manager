<h3>Test a Pickup Code</h3>
<form id="form-test-pcode">
<table>
	<tr>
		<td><input name="test-pcode" id="test-pcode" value="" placeholder="test a pickup code" size="15" /></td>
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

<h3>Add an Orphaned Contact</h3>
<form action="" id="form-add-orphaned-contact">
	<table>
		<tr>
			<td><input type="text" name="contact-email" id="contact-email" value="" size="30" placeholder="email"></td>
			<td><input type="text" name="contact-zipcode" id="contact-zipcode" value="" size="15" placeholder="zipcode"></td>
			<td><input type="text" name="contact-store-name" id="contact-store-name" value="" size="30" placeholder="store name"></td>
			<td><select name="contact-for-profit" id="contact-for-profit">
					<option value="0">Non Profit</option>
					<option value="1">For Profit - $$$</option>
				</select></td>
			<td><button class="button" id="add-contact" type="submit">Add Contact</button></td>
		</tr>
	</table>
</form>
<div class="output" id="output-add-contact"></div>
<hr/>

<h3>Search/Replace an Email</h3>
<form id="form-search-replace-email">
<table>
	<tr>
		<td><input name="search-email" id="search-email" value="" size="30" placeholder="search" /></td>
		<td><input name="replace-email" id="replace-email" value="" size="30" placeholder="replace" /></td>
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
			<td><input name="unsubscribe-email" id="unsubscribe-email" value="" size="30" placeholder="enter email to unsubscribe" /></td>
			<td><button class="button" id="unsubscribe-email" type="submit">Unsubscribe</button></td>
		</tr>
	</table>
</form>
<div class="output" id="output-unsubscribe"></div>