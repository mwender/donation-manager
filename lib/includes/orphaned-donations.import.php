<h3>Import a CSV</h3>
		<div id="progress-bar" style="display: none;"></div>
		<p id="import-percent"></p>

		<div id="import-table" style="display: none;">
			<h3>Import Preview for <span id="csv-name">One moment. Loading...</span> <span id="stats"></span></h3>
			<h4 id="run-import"></h4>
			<table class="widefat page" id="csvimport" style=" margin-bottom: 60px;">
				<thead><tr></tr></thead>
				<tbody></tbody>
			</table>
		</div>

		<table class="widefat page" id="csv_list">
			<col width="70%" /><col width="30%" />
			<thead>
				<tr>
					<th scope="col" class="manage-column">Title/Filename</th>
					<th scope="col" class="manage-column">&nbsp;</th>
				</tr>
			</thead>
			<tbody><tr class="alternate"><td colspan="5" style="text-align: center">One moment. Loading CSV list...</td></tr></tbody>
		</table>

		<div id="upload-csv">
			<h4>Upload a CSV</h4>
			<input id="upload_csv" type="text" size="36" name="upload_csv" value="" />
			<input id="upload_csv_button" type="button" value="Upload CSV" class="button" />
			<br />Upload a CSV file to the server.
		</div>