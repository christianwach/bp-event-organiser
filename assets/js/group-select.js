var bpeoGroupMsg = BpEventOrganiserSettings.group_privacy_message,
	bpeoToggleFlag = false,
	bpeoCurrStatus = '';

jQuery(function($){
	bpeoCurrStatus = $('#post-status-display').text();
	bpeoCurrVisibility = $('#post-visibility-display').text();
	bpeoSelect = $('#bp_event_organiser_metabox select');
	bpeoPrivateFlag = bpeoSelect.find('[title]').length;
	bpeoSubmit = $('#submitdiv .inside');

	bpeoToggle = function() {
		var notice = bpeoSubmit.find('.updated');

		if ( false === $.isEmptyObject( bpeoSelect.val() ) ) {
			bpeoToggleFlag = true;

			if ( bpeoPrivateFlag === 1 ) {
				$("#visibility-radio-private").prop("checked", true);
				$('#post-status-display').fadeOut('fast').text( postL10n.privatelyPublished ).fadeIn('fast');
				$('#post-visibility-display').fadeOut('fast').text( postL10n.private ).fadeIn('fast');
			}

			$('.misc-pub-post-status, .misc-pub-visibility').hide();
			$('#save-post').hide();
			$('#submitdiv .inside .error').show();

			if ( ! notice.length && typeof adminpage === 'undefined' ) {
				bpeoSubmit.prepend('<div class="updated"><p>' + bpeoGroupMsg + '</p></div>');
			} else {
				notice.fadeIn('fast');
			}

		} else if ( bpeoPrivateFlag === 0 && bpeoToggleFlag === true ) {
			bpeoToggleFlag = false;
			$("#visibility-radio-public").prop("checked", true);
			$('.misc-pub-post-status, .misc-pub-visibility').show();
			$('#post-status-display').fadeOut('fast').text( bpeoCurrStatus ).fadeIn('fast');
			$('.edit-post-status').show();
			$('#post-visibility-display').fadeOut('fast').text( bpeoCurrVisibility ).fadeIn('fast');
			$('#save-post').show();
			$('#submitdiv .inside .error').hide();

			if ( notice.length ) {
				notice.fadeOut('fast');
			}
		}
	}

	bpeoToggle();

	// do not show "Save Draft" button for public groups when clicking on the
	// "Cancel" button when toggled from the "Publish immediately" option
	//
	// overrides WP's updateText() function
	bpeoSubmit.on( "click", ".cancel-timestamp", function() {
		if ( bpeoSubmit.find('.updated').is(':visible') ) {
			$('#save-post').hide();
		}
	});

	bpeoFormatResponse = function(data) {
		return data.name || data.text;
	}

	bpeoFormatResult = function(data) {
		if (data.loading) return data.name;

		var markup = '<div style="clear:both;">' +
		'<div style="float:left;margin-right:8px;">' + data.avatar + '</div>' +
		'<div><span style="font-weight:600;">' + data.name + '</span> <em>(' + data.type + ')</em></div>';

		if (data.description) {
			markup += '<div style="font-size:.9em;line-height:1.9;">' + data.description + '</div>';
		}

		markup += '</div>';

		return markup;
	}

	bpeoSelect.select2({
		ajax: {
			method: 'POST',
			url: ajaxurl,
			dataType: 'json',
			delay: 500,
			data: function (params) {
				return {
					s: params.term, // search term
					action: 'bpeo_get_groups',
					nonce: $('#bp_event_organiser_nonce_field').val(),
					page: params.page
				};
			},
			cache: true,
			processResults: function (data, page) {
				// parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to
				// alter the remote JSON data
				return {
					results: data
				};
			},
		},
		escapeMarkup: function (markup) { return markup; }, // let our custom formatter work
		minimumInputLength: 3,
		templateResult: bpeoFormatResult,
		templateSelection: bpeoFormatResponse
	});

	bpeoSelect.on("select2:unselecting", function (e) {
		if ( 'Private' == e.params.args.data.title || true === e.params.args.data.private ) {
			bpeoPrivateFlag--;
		}
	});

	bpeoSelect.on("select2:selecting", function (e) {
		if ( 'Private' == e.params.args.data.title || true === e.params.args.data.private ) {
			bpeoPrivateFlag++;
		}
	});

	bpeoSelect.on("select2:unselect select2:select", function (e) {
		bpeoToggle();
	});

	//	For adding the silent event creation option
	var pageTitle = document.getElementsByClassName('admin-page-title')[0].innerHTML;
	// Event editing is already silent, so only do this if we're on an event creation page
	if ( pageTitle != 'Edit Event' ) {
		var searchField = document.getElementsByClassName( 'select2-search__field' )[0];
		if ( searchField ) {
			// Get the container for selected groups (the hidden input, not the visible search/select form)
			var groupOrganizerGroups = $( 'select[name="bp_group_organizer_groups[]"]' )[0];
			function checkGroupOrganizer() {
				// If a group has been selected, and we haven't already added the silent-ness
				// checkbox, then add it
				if ( groupOrganizerGroups.options.length >=1 && document.getElementById( "bp_event_organiser_silent" ) == null ) {
					addSilentForm();
				}
				// If no group is selected, remove the form
				if ( document.getElementsByClassName( 'select2-selection__choice' ).length == 0 ) {
					removeSilentForm();
				}
			}
			function addSilentForm() {
				var publishing = document.getElementById( 'publishing-action' );
				$( publishing ).before( '<p id="bpeo-silent-wrapper"><input type="checkbox" value="silent" id="bp_event_organiser_silent" name="bpeo-silent"></input> <strong>Silent create</strong> (notifications will not be sent to subscribed group members)</p>' );
			}
			function removeSilentForm() {
				$( '#bpeo-silent-wrapper' ).remove();
			}
			// Check for selected groups on page load
			checkGroupOrganizer();
			// Check for selected groups when the user clicks into and out of the group search input
			searchField.addEventListener( 'focus', checkGroupOrganizer );
			searchField.addEventListener( 'blur', checkGroupOrganizer );
		}
	}
});
