jQuery(function($) {
	
	function gbl_advertiser_added(name) {
		let added = false;
		$.each($("#gbl_advertisers_input .gbl_advertiser_name"), function(index, value) {
			if (name === value.innerText) { added = true; return; }
		});
		return added;
	}

	$("#gbl_add_advertiser").click(function(e) {
		let $item = $('#advertiser_list');
		let name = $item.val();
		let url = $('option:selected', $item).attr('url');
		let mid = $('option:selected', $item).attr('mid');

		if (!gbl_advertiser_added(name)) {
			let ad = '<div class="gbl_row"> \
							<div class="gbl_col gbl_col_1 gbl_advertiser_name">' + name + '</div> \
							<div class="gbl_col gbl_col_2"><a target="_blank" mid="' + mid + '" href="' + url + '">' + url + '</a></div>';						
			ad += '<input type="hidden" name="gbl_advertisers[name][]" value="' + name + '" />';
			ad += '<input type="hidden" name="gbl_advertisers[url][]" value="' + url + '" />';
			ad += '<input type="hidden" name="gbl_advertisers[mid][]" value="' + mid + '" />';
			ad += '</div>';

			$("#gbl_advertisers_input").append(ad);
		} else {
			alert("You have already added the advertiser in your list.");
		}
		
		let count = $("#gbl_advertisers_input .gbl_advertiser_name").length;
		$("#gbl_advertisers_input").attr('count', count);
	});
});