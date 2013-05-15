var getGroups = (function(){
	var result = {};
	jQuery('div.ginput_complex').each(function(i,e) {
		var fieldName = e.id;
		jQuery(this).find('input, select, textarea').each(function(i,e) {
			if(result[fieldName] != undefined){
				result[fieldName] += ' '+e.name;
			} else {
				result[fieldName] = e.name
			}
		});
	});
	//console.log(result);
	return result;
});
