X.actionLoaded(function (event, fn, params){
	
	fn("div.theme div.actions a.select").click(function(event){
		      
		var themeName = $(event.currentTarget).attr("data-theme");
		fn("input[name='theme']").val(themeName);
		return false;
  	});
});