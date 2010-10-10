/**
 * Config object
 */
var oConfig = {
	sScriptURL   : '',
	sLabelDelete : ''
}


/**
 * Get selected entries
 */
function sGetSelected() {
	var sSeleted = '0';
	var sPrefix  = 'tx_spbettercontact_log_check_';
	var sID      = '';

	$$('.tx_spbettercontact_log_check_checked').each(function(oElement) {
		sID = $(oElement).identify();
		sID = sID.substr(sPrefix.length, sID.length);
		sSeleted += ',' + sID;
	});

	return sSeleted;
}


/**
 * Observe several elements if DOM is loaded
 */
document.observe("dom:loaded", function() {


	/**
	 * Observe all checkboxes
	 */
	$$('.tx_spbettercontact_log_check').each(function(oElement) {
		var sClass = 'tx_spbettercontact_log_check_checked';

		$(oElement).observe('click', function() {
			if ($(oElement).hasClassName(sClass)) {
				$(oElement).removeClassName(sClass);
			} else {
				$(oElement).addClassName(sClass);
			}
		});
	});


	/**
	 * Observe checkbox to activate / deactivate all
	 */
	$('tx_spbettercontact_log_checkall').observe('click', function() {
		var sClass    = 'tx_spbettercontact_log_check_checked';
		var sClassAll = 'tx_spbettercontact_log_checkall_checked';
		var oElement  = $('tx_spbettercontact_log_checkall');

		if ($(oElement).hasClassName(sClassAll)) {
			$$('.tx_spbettercontact_log_check').each(function(oCheckbox) {
				$(oCheckbox).removeClassName(sClass);
			});
			$('tx_spbettercontact_log_checkall').removeClassName(sClassAll);
		} else {
			$$('.tx_spbettercontact_log_check').each(function(oCheckbox) {
				$(oCheckbox).addClassName(sClass);
			});
			$('tx_spbettercontact_log_checkall').addClassName(sClassAll);
		}
	});


	/**
	 * Observe all expand links
	 */
	$$('.tx_spbettercontact_log_expand').each(function(oElement) {
		var sClass  = 'tx_spbettercontact_log_expanded';
		var oParent = $(oElement).up().up();

		$(oElement).observe('click', function() {
			if ($(oParent).hasClassName(sClass)) {
				$(oParent).removeClassName(sClass);
			} else {
				$(oParent).addClassName(sClass);
			}
		});
	});


	/**
	 * Observe all rows for hover effect
	 */
	$('tx_spbettercontact_log_body_content').select('tr').each(function(oElement) {
		var sClass  = 'tx_spbettercontact_log_hover';

		$(oElement).observe('mouseout', function() {
			$(oElement).removeClassName(sClass);
		});
		$(oElement).observe('mouseover', function() {
			$(oElement).addClassName(sClass);
		});
	});


	/**
	 * Generate a CSV file from selected entries
	 */
	$('tx_spbettercontact_log_csv').observe('click', function() {
		var sURL = oConfig.sScriptURL + 'csv=1&rows=' + sGetSelected();
		$('tx_spbettercontact_log_csv').writeAttribute('href', sURL);
	});


	/**
	 * Delete selected rows
	 */
	$('tx_spbettercontact_log_delete').observe('click', function(oEvent) {
		var sSelected = sGetSelected();

		if (sSelected == '0' || !confirm(oConfig.sLabelDelete)) {
			Event.stop(oEvent);
			return false;
		}

		var sURL = oConfig.sScriptURL + 'del=1&rows=' + sSelected;
		$('tx_spbettercontact_log_delete').writeAttribute('href', sURL);
	});

});