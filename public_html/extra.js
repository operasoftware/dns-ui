/*
##
## Copyright 2013-2018 Opera Software AS
##
## Licensed under the Apache License, Version 2.0 (the "License");
## you may not use this file except in compliance with the License.
## You may obtain a copy of the License at
##
## http://www.apache.org/licenses/LICENSE-2.0
##
## Unless required by applicable law or agreed to in writing, software
## distributed under the License is distributed on an "AS IS" BASIS,
## WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
## See the License for the specific language governing permissions and
## limitations under the License.
##
*/
'use strict';

// Remember the last-selected tab in a tab group
$(function() {
	if(sessionStorage) {
		$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
			//save the latest tab
			sessionStorage.setItem('lastTab' + location.pathname, $(e.target).attr('href'));
		});

		//go to the latest tab, if it exists:
		var lastTab = sessionStorage.getItem('lastTab' + location.pathname);

		if (lastTab) {
			$('a[href=' + lastTab + ']').tab('show');
		} else {
			$('a[data-toggle="tab"]:first').tab('show');
		}
	}

	get_tab_from_location();
	window.onpopstate = function(event) {
		get_tab_from_location();
	}

	// Do the location modifying code after all other setup, since we don't want the initial loading to trigger this
	$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
		if(history) {
			history.replaceState(null, null, e.target.href);
		} else {
			window.location.hash = e.target.hash;
		}
	});

	// Enable link to tab
	function get_tab_from_location() {
		var url = document.location.toString();
		if(url.match('#')) {
			$('.nav-tabs a[href="#'+url.split('#')[1]+'"]').tab('show');
		}
	}
});

$(function() {
	// Provide editing functionality on the zone page
	$('form.zoneedit').each(function() {
		var form = $(this);
		var updates = [];
		var typeselect;
		var enabledselect;
		var max_rrsetnum = 0;
		var pages = 1;
		var per_page = parseInt($('#maxperpage').val(), 10);

		// Initialize the form, setting events where they are needed
		typeselect = $('#new_type').clone();
		typeselect.attr('id', null);
		enabledselect = $('#new_enabled').clone();
		enabledselect.attr('id', null);
		$('#new_ttl').data('default-value', $('#new_ttl').val());
		$('option:first-of-type', typeselect).remove();
		$('button.delete-rr', form).on('click', function() { delete_rr($(this)); });
		$('td.name', form).on('click', function() { make_editable($(this), 'name'); });
		$('td.type', form).on('click', function() { make_editable($(this), 'type'); });
		$('td.ttl', form).on('click', function() { make_editable($(this), 'ttl'); });
		$('td.content', form).on('click', function() { make_editable($(this), 'content'); });
		$('td.enabled', form).on('click', function() { make_editable($(this)); });
		$('td.comment', form).on('click', function() { make_editable($(this), 'comment'); });
		$('tbody tr', form).each(function() { max_rrsetnum = Math.max(max_rrsetnum, parseInt($(this).data('rrsetnum'), 10)); });
		$('#new_name').on('keyup', function(event) { validate_new(); if(event.which == 13) add_new(); });
		$('#new_name').on('change', function() { validate_new(); });
		$('#new_type').on('change', function() { update_content_pattern($(this)); validate_new(); });
		$('#new_ttl').on('keyup', function(event) { validate_new(); if(event.which == 13) add_new(); });
		$('#new_ttl').on('change', function() { validate_new(); });
		$('#new_content').on('keyup', function(event) { validate_new(); if(event.which == 13) add_new(); });
		$('#new_content').on('change', function() { validate_new(); });
		$('#new_comment').on('keyup', function(event) { validate_new(); if(event.which == 13) add_new(); });
		$('#new_comment').on('change', function() { validate_new(); });
		$('#new_add').on('click', function() { add_new(); });
		$('#filter').on('keyup', function() { filter_recordsets(this.value); });
		$('#updates').hide().removeClass('hide');
		$('#zonesubmit').on('click', function(e) { submit_zone_update(e); });
		paginate(max_rrsetnum);

		// Mark the row as deleted
		function delete_rr(button) {
			make_editable(button);
			button.empty();
			var tr = button.closest('tr');
			if(tr.data('delete')) {
				button.append($('<span>').addClass('glyphicon').addClass('glyphicon-trash'));
				button.append(' Delete');
				tr.removeClass('delete');
				tr.data('delete', false);
			} else {
				button.text('Undelete');
				tr.addClass('delete');
				tr.data('delete', true);
			}
			update_changed(button);
		}

		// Make the selected row editable and focus the chosen cell
		function make_editable(element, cell) {
			// Get row details of this rrset
			var tr = element.closest('tr');
			var rrset = tr.data();
			if(rrset.editing) return;
			// Get all rows in this rrset
			var table = element.closest('table');
			var rows = $('tr[data-rrsetnum="' + rrset.rrsetnum + '"]', table);
			// Convert fields in these rows into text input elements
			rows.each(function() {
				$(this).data('editing', true);
				$('td.name', this).each(function() { inputify($(this)); });
				$('td.type', this).each(function() { selectify($(this), typeselect); });
				$('td.ttl', this).each(function() { inputify($(this)); });
				$('td.content', this).each(function() { inputify($(this)); });
				$('td.enabled', this).each(function() { selectify($(this), enabledselect); });
				$('td.comment', this).each(function() { inputify($(this)); });
			});
			update_content_pattern($('td.type select', rows));
			// Focus the field in the selected cell
			if(cell) {
				$('td.' + cell + ' input', tr).focus();
			}
		}

		// Convert the table cell content into a select element, based on the given template
		function selectify(element, template) {
			var text = element.text();
			var select = template.clone().each(function() { this.value = text; });
			$(select).on('change', function() { update_content_pattern($(this)); update_changed($(this)); });
			element.empty();
			element.data('oldvalue', text);
			element.append(select);
		}

		// Convert the table cell content into a text input
		function inputify(element) {
			$('span.zone-hint', element).remove();
			var text = element.text();
			var input = document.createElement('input');
			input.type = 'text';
			input.value = text;
			$(input).attr('required', $('#new_' + element.attr('class')).attr('required'));
			$(input).attr('pattern', $('#new_' + element.attr('class')).attr('pattern'));
			$(input).on('input', function() { update_changed($(this)); });
			element.empty();
			element.data('oldvalue', text);
			element.append(input);
		}

		function update_content_pattern(select) {
			var pattern = $('option:selected', select).data('content-pattern');
			if(pattern) {
				var tr = select.closest('tr');
				$('td.content input', tr).attr('pattern', pattern);
				// Update pattern of all rows in this rrset
				if(!tr.data('rrsetnum')) return;
				var table = tr.closest('table');
				$('tr[data-rrsetnum="' + tr.data('rrsetnum') + '"] td.content input', table).attr('pattern', pattern);
			}
		}

		// Work out what changes have been made, check their validity and populate the updates list for the edited recordset
		function update_changed(element) {
			// Get row details of this rrset
			var tr = element.closest('tr');
			var rrset = tr.data();
			// Get all rows in this rrset
			var table = element.closest('table');
			var rows = $('tr[data-rrsetnum="' + rrset.rrsetnum + '"]', table);
			var primary_row = $('tr[data-rrsetnum="' + rrset.rrsetnum + '"].primary', table);
			var rrsetnum = parseInt(rrset.rrsetnum, 10);
			var rrsetchanged = false;
			var valid = true;
			var update = {};
			if(rows.first().data('newrrset')) {
				update.action = 'add';
				update.name = rrset.name = String($('td.name input', rows).val());
				update.type = rrset.type = String($('td.type select', rows).val());
			} else {
				update.action = 'update';
				update.oldname = String(rrset.name);
				update.oldtype = String(rrset.type);
			}
			update.ttl = $('td.ttl input', rows).val();
			update.comment = $('td.comment input', rows).val();
			var li = document.createElement('li');
			li.id = 'rrsetreport' + rrsetnum;
			var strong = document.createElement('strong');
			var rrset_label = [rrset.name, rrset.type].join(' ');
			$(strong).text(rrset_label + ':');
			li.appendChild(strong);
			update.records = [];
			var activerows = 0;
			var enabledrows = 0;
			// Loop through all resource records in this rrset.
			rows.each(function() {
				var record = {};
				$(this).toggleClass('disabled', $('td.enabled select', this).val() == 'No');
				if($(this).data('newrow')) {
					if(!$(this).data('delete')) {
						// This is a newly-added row, simply show the values.
						rrsetchanged = true;
						var ttl = $('td.ttl input', this).val();
						var content = $('td.content input', this).val();
						var enabled = $('td.enabled select', this).val();
						var comment = $('td.comment input', this).val();
						li.appendChild(document.createTextNode(' '));
						var span = document.createElement('span');
						span.appendChild(document.createTextNode('Added new resource record, Content = '));
						var em = document.createElement('em');
						display_value(em, content, 'content', update.type);
						span.appendChild(em);
						span.appendChild(document.createTextNode(', Enabled = '));
						var em = document.createElement('em');
						$(em).text(enabled);
						span.appendChild(em);
						if(comment) {
							console.log(comment);
							span.appendChild(document.createTextNode(', Comment = "'));
							var em = document.createElement('em');
							$(em).text(comment);
							span.appendChild(em);
							span.appendChild(document.createTextNode('"'));
						}
						span.appendChild(document.createTextNode('.'));
						li.appendChild(span);
						record['content'] = content;
						record['enabled'] = enabled;
						update.records.push(record);
						activerows++;
						if(enabled == 'Yes') enabledrows++;
					}
				} else {
					// This is an existing row - show the changes.
					var rrspan = document.createElement('span');
					var rrchanged = false;

					// Show the (old) rr content to identify which rr in the rrset is changed.
					// Useful in general and for instance in case of multiple A records.
					$(rrspan).text(' [' + $('td.content', this).data('oldvalue') + ']');

					// Loop through all fields to see what is changed.
					$('input,select', this).each(function() {
						var ucname = this.parentNode.className.substr(0, 1).toUpperCase() + this.parentNode.className.substr(1);
						if(ucname == 'Ttl') ucname = 'TTL';
						if(!this.checkValidity()) {
							// Field is invalid. Don't show the change, just show the new value as invalid
							valid = false;
							rrchanged = true;
							rrspan.appendChild(document.createTextNode(' '));
							var span = document.createElement('span');
							span.appendChild(document.createTextNode(ucname + ' '));
							var em = document.createElement('em');
							$(em).text($(this).val());
							span.appendChild(em);
							span.appendChild(document.createTextNode(' is invalid.'));
							$(span).addClass('text-danger');
							rrspan.appendChild(span);
						} else if($(this).parent().data('oldvalue') != $(this).val()) {
							// Show that the field has changed from old value to new
							rrchanged = true;
							rrspan.appendChild(document.createTextNode(' '));
							var span = document.createElement('span');
							$(span).text(ucname + ' changed from ');
							var em = document.createElement('em');
							display_value(em, $(this).parent().data('oldvalue'), this.parentNode.className, update.type);
							span.appendChild(em);
							span.appendChild(document.createTextNode(' to '));
							var em = document.createElement('em');
							display_value(em, $(this).val(), this.parentNode.className, update.type);
							span.appendChild(em);
							rrspan.appendChild(span);
						}
						switch(this.parentNode.className) {
						case 'name':
						case 'type':
						case 'ttl':
						case 'comment':
							update[this.parentNode.className] = $(this).val();
							break;
						case 'content':
						case 'enabled':
							record[this.parentNode.className] = $(this).val();
							break;
						}
					});
					if($(this).data('delete')) {
						rrchanged = true;
						rrspan.appendChild(document.createTextNode(' '));
						var span = document.createElement('span');
						$(span).text('Resource record deleted.');
						$(span).addClass('text-warning');
						rrspan.appendChild(span);
						record['delete'] = true;
					} else {
						activerows++;
						if(record['enabled'] == 'Yes') enabledrows++;
					}
					if(rrchanged) {
						rrsetchanged = true;
						li.appendChild(rrspan);
					}
					update.records.push(record);
				}
				if(!form.data('local-zone') && (update.type == 'A' || update.type == 'AAAA') && valid) {
					var local_ip = false;
					$('td.content input', this).each(function() {
						if(update.type == 'A') {
							var ranges = form.data('local-ipv4-ranges').split(' ');
						} else {
							var ranges = form.data('local-ipv6-ranges').split(' ');
						}
						var addr = ipaddr.parse(this.value);
						for(var i = 0; i < ranges.length; i++) {
							var range = ipaddr.parseCIDR(ranges[i]);
							if(addr.match(range)) local_ip = this.value;
						}
					});
					if(local_ip != false) {
						li.appendChild(document.createTextNode(' '));
						var span = document.createElement('span');
						var strong = document.createElement('strong');
						strong.appendChild(document.createTextNode('Warning:'));
						span.appendChild(strong);
						span.appendChild(document.createTextNode(' ' + local_ip + ' is a local IP address. Adding it to a public zone does '));
						var strong = document.createElement('strong');
						strong.appendChild(document.createTextNode('not'));
						span.appendChild(strong);
						span.appendChild(document.createTextNode(' make it accessible externally. Only proceed if you know what you are doing.'));
						$(span).addClass('text-warning');
						li.appendChild(span);
					}
				}
			});
			if(rrset.type == 'CNAME' && enabledrows > 1) {
				valid = false;
				li.appendChild(document.createTextNode(' '));
				var span = document.createElement('span');
				var strong = document.createElement('strong');
				strong.appendChild(document.createTextNode('Error:'));
				span.appendChild(strong);
				span.appendChild(document.createTextNode(' Multiple records of singleton record type.'));
				$(span).addClass('text-danger');
				li.appendChild(span);
			}
			if(activerows == 0) {
				primary_row.addClass('rrset-delete');
			} else {
				primary_row.removeClass('rrset-delete');
			}
			if(enabledrows == 0) {
				primary_row.addClass('rrset-disable');
			} else {
				primary_row.removeClass('rrset-disable');
			}
			if(!valid) update.invalid = true;
			if(rrsetchanged) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'updates[]';
				input.value = JSON.stringify(update);
				li.appendChild(input);
				updates[rrsetnum] = update;
				var listitem = $('#updates_list li#rrsetreport' + rrsetnum);
				if(listitem.length == 0) {
					$('#updates_list').append(li);
				} else {
					listitem.replaceWith(li);
				}
			} else {
				var listitem = $('#updates_list li#rrsetreport' + rrsetnum);
				if(listitem.length > 0) {
					listitem.remove();
				}
				updates[rrsetnum] = null;
			}
			// Check for validity
			var allvalid = true;
			$('input', $('#updates_list')).each(function() {
				var update = JSON.parse(this.value);
				if(update.invalid) allvalid = false;
			});
			// Check for RRset name collision across all RRsets
			var collision = false;
			var rrset_hash = {};
			var singleton_hash = {};
			$('#collisions_list').empty();
			$('tbody tr.primary', table).each(function() {
				if($(this).hasClass('rrset-disable')) return;
				if($(this).data('editing')) {
					var name = $('td.name input', this).val();
					var type = $('td.type select', this).val();
				} else {
					var name = $(this).data('name');
					var type = $(this).data('type');
				}
				if(rrset_hash[name + ' ' + type]) {
					var li = document.createElement('li');
					$(li).text('Multiple resource recordsets for ' + name + ' ' + type);
					$(li).addClass('text-danger');
					$('#collisions_list').append(li);
					collision = true;
				}
				rrset_hash[name + ' ' + type] = true;
				if(singleton_hash.hasOwnProperty(name)) {
					if(singleton_hash[name] || type == 'CNAME' || type == 'DNAME') {
						// We have a singleton recordset that matches another recordset of the same name
						var li = document.createElement('li');
						$(li).text('Singleton type record exists with same name as other types for ' + name);
						$(li).addClass('text-danger');
						$('#collisions_list').append(li);
						collision = true;
					}
				}
				singleton_hash[name] = (type == 'CNAME' || type == 'DNAME');
			});
			$('#zonesubmit').attr('disabled', !allvalid || collision);
			if($('#updates_list li').length == 0) {
				$('#updates').hide(200);
				$(window).off('beforeunload');
			} else {
				$('#updates').show(200);
				$(window).on('beforeunload', function() { return 'You have unsaved changes.'; });
			}
		}

		/*
		* Display the specified value inside the provided element. Show "" if the value is empty.
		* Do some special processing for some cases.
		*/
		function display_value(element, value, value_type, record_type) {
			$(element).text(value || '""');
			if(value_type == 'content') {
				switch(record_type) {
				case 'CNAME':
				case 'DNAME':
				case 'NS':
				case 'PTR':
				case 'MX':
				case 'SRV':
					// Make it clear that without a trailing . we will automatically append
					// the domain name to the content for these record types
					if(value.endsWith('@')) {
						var strong = document.createElement('strong');
						$(strong).text(form.data('zone')).addClass('zone-hint');
						element.appendChild(strong);
					} else if(!value.endsWith('.')) {
						var strong = document.createElement('strong');
						$(strong).text('.' + form.data('zone')).addClass('zone-hint');
						element.appendChild(strong);
					}
					break;
				}
			}
		}

		/*
		* Check that all of the "new" fields are valid. If so, enable the add button.
		*/
		function validate_new() {
			var valid = true;
			$('input,select', $('#new_row')).each(function() {if(!this.checkValidity()) valid = false;});
			$('#new_add').prop('disabled', !valid);
			return valid;
		}

		/*
		* Add a new resource record to the table and the updates list
		*/
		function add_new() {
			if(!validate_new()) return;
			var name = $('#new_name').val();
			var type = $('#new_type').val();
			var ttl = $('#new_ttl').val();
			var content = $('#new_content').val();
			var enabled = $('#new_enabled').val();
			var comment = $('#new_comment').val();
			var tr = document.createElement('tr');
			// Is there an existing rrset with this name/type?
			var table = $('#new_name').closest('table');
			var rows = $('tr[data-name="' + name + '"][data-type="' + type + '"]', table);
			if(rows.length == 0) {
				// Add a new rrset
				tr.className = 'primary';
				var td = document.createElement('td');
				td.className = 'name';
				td.appendChild(document.createTextNode(name));
				tr.appendChild(td);
				var td = document.createElement('td');
				td.className = 'type';
				td.appendChild(document.createTextNode(type));
				tr.appendChild(td);
				var td = document.createElement('td');
				td.className = 'ttl';
				td.appendChild(document.createTextNode(ttl));
				tr.appendChild(td);
				tr.dataset.newrrset = 1;
				max_rrsetnum++;
				var rrsetnum = max_rrsetnum;
			} else {
				// Add this rr to the existing rrset
				$('td.name, td.type, td.ttl, td.comment', rows).prop('rowspan', function(i, rs) { return rs + 1; });
				var rrsetnum = rows.data('rrsetnum');
				// Show rrset if it is currently hidden
				rows.removeClass('hidden');
			}
			tr.dataset.name = name;
			tr.dataset.type = type;
			tr.dataset.rrsetnum = rrsetnum;
			tr.dataset.newrow = 1;
			var td = document.createElement('td');
			td.className = 'content';
			td.appendChild(document.createTextNode(content));
			tr.appendChild(td);
			var td = document.createElement('td');
			td.className = 'enabled';
			td.appendChild(document.createTextNode(enabled));
			tr.appendChild(td);
			var td = document.createElement('td');
			td.className = 'actions';
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'btn btn-default btn-xs delete-rr';
			var span = document.createElement('span');
			span.className = 'glyphicon glyphicon-trash';
			button.appendChild(span);
			button.appendChild(document.createTextNode(' Delete'));
			$(button).on('click', function(){ delete_rr($(this)); });
			td.appendChild(button);
			tr.appendChild(td);
			if(rows.length == 0) {
				var td = document.createElement('td');
				td.className = 'comment';
				td.appendChild(document.createTextNode(comment));
				tr.appendChild(td);
				// Append the new rrset
				$('tbody', table).append($(tr));
				make_editable($(tr.childNodes[0]));
			} else {
				// Add row after existing rrset rows
				$(tr).insertAfter(rows.last());
				if(rows.data('editing')) {
					// This rrset is already in edit mode - do the same with this row
					$('td.content', tr).each(function() { inputify($(this)); });
					$('td.enabled', tr).each(function() { selectify($(this), enabledselect); });
					$(tr).data('editing', true);
				} else {
					// Put the rrset into edit mode
					make_editable($(tr.childNodes[0]));
				}
			}
			update_changed($(tr));

			$('#new_name').val('');
			$('#new_type').val('');
			$('#new_content').val('');
			$('#new_enabled').val('Yes');
			$('#new_comment').val('');

			$('#new_name').focus();
		}

		function submit_zone_update(event) {
			var submitButton = $('#zonesubmit');
			var updateForm = submitButton[0].form;
			if(!updateForm.checkValidity()) return;
			if(submitButton.data('submitted') === true) {
				event.preventDefault();
			} else {
				submitButton.data('submitted', true);
				submitButton.addClass('disabled');
			}
			if($(event.target).val() == 'request') {
				$(window).off('beforeunload');
				return;
			}
			$('#errors').empty();
			var actions = [];
			$('input[name="updates[]"]', $(event.target).closest('form')).each(function() {
				actions.push(JSON.parse(this.value));
			});
			$.ajax({
				url: "../api/v2/zones/" + encodeURIComponent(form.data('zone')),
				method: "PATCH",
				data: JSON.stringify({actions: actions, comment: $('#comment').val()}),
				contentType: "application/json",
				dataType: "json"
			}).done(function() {
				$(window).off('beforeunload');
				window.location.href = window.location.pathname;
			}).fail(function(response) {
				submitButton.data('submitted', false);
				submitButton.removeClass('disabled');
				var data = JSON.parse(response.responseText);
				for(var i = 0, error; error = data.errors[i]; i++) {
					$('#errors').append(
						$('<div>').addClass('alert').addClass('alert-danger').append(
							$('<strong>').text(error.userMessage + ': ')
						).append(error.internalMessage)
					);
				}
			});
			event.preventDefault();
		}

		function paginate(total) {
			pages = Math.ceil(total / per_page);
			create_pagination_ui();
		}

		function create_pagination_ui() {
			$('nav', form).each(function() {
				$(this).empty();
				//if(pages <= 1) return;
				var ul = $('<ul>');
				ul.addClass('pagination');
				// Previous
				var li = $('<li>');
				li.addClass('disabled');
				var a = $('<a>');
				a.on('click', function(){return false});
				a.prop('href', '#');
				a.prop('aria-label', 'Previous');
				var span = $('<span>');
				span.prop('aria-hidden', 'true');
				span.text('«');
				a.append(span);
				li.append(a);
				ul.append(li);
				// Pages
				for(var i = 1; i <= pages; i++) {
					var li = $('<li>');
					li[0].dataset.page = i;
					var a = $('<a>');
					a.on('click', function(){show_page(parseInt(this.parentNode.dataset.page, 10));return false});
					a.prop('href', '#');
					a.text(i);
					if(i == 1) {
						li.addClass('active');
						var span = $('<span>');
						span.addClass('sr-only');
						span.text(' (current)');
						a.append(span);
					}
					li.append(a);
					ul.append(li);
				}
				// Next
				var li = $('<li>');
				var a = $('<a>');
				if(pages <= 1) {
					li.addClass('disabled');
				} else {
					a.on('click', function(){show_page(2);return false});
				}
				a.prop('href', '#');
				a.prop('aria-label', 'Next');
				var span = $('<span>');
				span.prop('aria-hidden', 'true');
				span.text('»');
				a.append(span);
				li.append(a);
				ul.append(li);
				$(this).append(ul);
			});
		}

		function show_page(page) {
			// Update list
			var count = 0;
			$('table tbody tr', form).not('.filtered').each(function() {
				if($(this).hasClass('primary')) {
					count++;
				}
				if(Math.ceil(count / per_page) == page) {
					$(this).removeClass('hidden');
				} else {
					$(this).addClass('hidden');
				}
			});

			// Update navigation
			$('nav ul.pagination li', form).removeClass('active').removeClass('disabled');
			$('nav ul.pagination li[data-page="' + page + '"]').addClass('active');
			$('nav ul.pagination li:first-of-type a, nav ul.pagination li:last-of-type a').off();
			if(page == 1) {
				$('nav ul.pagination li:first-of-type').addClass('disabled');
			} else {
				$('nav ul.pagination li:first-of-type a').on('click', function(){show_page(page - 1);return false});
			}
			if(page == pages) {
				$('nav ul.pagination li:last-of-type').addClass('disabled');
			} else {
				$('nav ul.pagination li:last-of-type a').on('click', function(){show_page(page + 1);return false});
			}
		}

		// Get search filter from querystring
		var urlParams = {};
		if(window.location.search) {
			var match,
				pl     = /\+/g,  // Regex for replacing addition symbol with a space
				search = /([^&=]+)=?([^&]*)/g,
				decode = function (s) { return decodeURIComponent(s.replace(pl, " ")); },
				query  = window.location.search.substring(1);

			while(match = search.exec(query)) {
			   urlParams[decode(match[1])] = decode(match[2]);
			}
			console.log(urlParams);
		}

		$('table thead tr th', form).each(function() {
			// Add filter button
			if(this.className == 'actions' || this.className == 'enabled') return;
			$(this).css('position', 'relative');
			var button = $('<button>');
			button.prop('type', 'button');
			button.addClass('btn').addClass('btn-default').addClass('btn-xs');
			button.css('position', 'absolute').css('right', '5px').css('top', '4px');
			var span = $('<span>');
			span.addClass('glyphicon').addClass('glyphicon-filter');
			button.append(span);
			button.on('click', function(){show_filter($(this))});
			$(this).append(button);
			var input = $('<input>');
			input.type = 'text';
			input.css('position', 'absolute').css('right', '5px').css('top', '5px');
			input.css('width', '40%').css('min-width', '6em').css('padding', '0').css('line-height', '12px');
			input.prop('placeholder', 'Filter');
			input.hide();
			input.on('input', function(){sync_filters($(this));filter_recordsets()});
			input.on('blur', function(){update_filter_visibility($(this), true)});
			if(urlParams[this.className]) {
				input.val(urlParams[this.className]);
			}
			$(this).append(input);
			update_filter_visibility(input, true);
		});
		filter_recordsets();

		function show_filter(button) {
			button.hide();
			var input = button.parent().find('input');
			input.show('fast');
			input.focus();
		}

		function update_filter_visibility(input, blur) {
			$('th.' + input.parent().prop('class') + ' input', form).each(function() {
				if(input.val() == '' && blur) {
					$(this).hide('fast');
					var button = $(this).parent().find('button');
					button.show();
				} else {
					$(this).show();
					var button = $(this).parent().find('button');
					button.hide();
				}
			});
		}

		function sync_filters(input) {
			// We have 2 search fields for each column - one in the actual thead, and one in the static clone of the thead
			// We need to synchronize their values
			$('th.' + input.parent().prop('class') + ' input', form).val(input.val()).each(function() {update_filter_visibility($(this), false);});
		}

		function filter_recordsets() {
			var querystring_array = [];
			var filters = {};
			var check = {};
			$('table', form).first().find('thead th input').each(function() {
				if(this.value != '') {
					querystring_array.push(encodeURIComponent($(this).parent().prop('class')) + '=' + encodeURIComponent(this.value));
					filters[$(this).parent().prop('class')] = this.value.toLowerCase();
					check[$(this).parent().prop('class')] = 0;
				}
			});
			if(querystring_array.length == 0) {
				var querystring = '';
			} else {
				var querystring = '?' + querystring_array.join('&');
			}
			window.history.replaceState(null, null, window.location.pathname + querystring + window.location.hash);
			var checkJSON = JSON.stringify(check);
			var total = 0;
			var rrsetnum = null;
			var rrsetrows = null;
			var match = null;
			$('table tbody tr', form).each(function() {
				var tr = $(this);
				if(this.dataset.rrsetnum != rrsetnum) {
					if(rrsetnum != null) {
						total += filter_recordset(match, rrsetrows);
					}
					rrsetrows = [];
					rrsetnum = this.dataset.rrsetnum;
					match = JSON.parse(checkJSON);
				}
				Object.keys(filters).forEach(function(key,index) {
					var value = filters[key];
					var td = tr.find('td.' + key);
					if(td.text().toLowerCase().indexOf(value) > -1) {
						match[key]++;
					} else if(td.data('oldvalue') && td.data('oldvalue').toLowerCase().indexOf(value) > -1) {
						match[key]++;
					}
				});
				rrsetrows.push(tr);
			});
			if(rrsetnum != null) {
				total += filter_recordset(match, rrsetrows);
			}
			//console.log(total + ' recordset(s) matched filter');
			paginate(total);
			show_page(1);
		}

		function filter_recordset(match, rrsetrows) {
			var match_all = true;
			Object.keys(match).forEach(function(key,index) {
				if(match[key] == 0) match_all = false;
			});
			var length = rrsetrows.length;
			if(match_all) {
				for(var i = 0; i < length; i++) {
					rrsetrows[i].removeClass('filtered');
				}
				return 1;
			} else {
				for(var i = 0; i < length; i++) {
					rrsetrows[i].addClass('filtered');
				}
				return 0;
			}
		}
	});

	$('form.pending_update').each(function() {
		var form = $(this);
		$('button[name="reject_update"]', form).on('click', function(e) {
			var reason = prompt("You may provide a reason for rejecting this change, which will be seen by the requester.\nLeave blank to provide no reason.");
			if(reason === null) e.preventDefault();
			$('input[name="reject_reason"]', form).val(reason);
		});
	});

	// Add template button functionality on zone add and zone soa edit form
	$('form.zoneadd, form.zoneeditsoa').each(function() {
		var form = $(this);
		$('button.soa-template[data-default="1"], button.ns-template[data-default="1"]', form).each(function() {
			$.each(this.dataset, function(index, value) { $('#' + index).val(value) });
			$(this).removeClass('btn-default').addClass('btn-success');
		});
		$('button.soa-template, button.ns-template', form).on('click', function() {
			$.each(this.dataset, function(index, value) { $('#' + index).val(value) });
			$('button', this.parentNode).removeClass('btn-success').addClass('btn-default');
			$(this).removeClass('btn-default').addClass('btn-success');
		});

		$('input#ipv4_zone_prefix').on('keyup', function(event) { if(event.which == 13) prefill_reverse_ipv4_zone($(this)) });
		$('button#ipv4_zone_create').on('click', function() { prefill_reverse_ipv4_zone($('input#ipv4_zone_prefix')) });
		$('input#ipv6_zone_prefix').on('keyup', function(event) { if(event.which == 13) prefill_reverse_ipv6_zone($(this)) });
		$('button#ipv6_zone_create').on('click', function() { prefill_reverse_ipv6_zone($('input#ipv6_zone_prefix')) });

		function prefill_reverse_ipv4_zone(field) {
			if(!field[0].checkValidity()) return;
			var prefix = field.val().replace(/\.$/, '');
			var parts = prefix.split('.').reverse();
			$('#name').val(parts.join('.') + '.in-addr.arpa');
			$('a[href=#create]').tab('show');
		}
		function prefill_reverse_ipv6_zone(field) {
			if(!field[0].checkValidity()) return;
			var prefix = field.val();
			var parts = prefix.split(':');
			for(var i = 0; i < parts.length - 1; i++) {
				parts[i] = String('0000' + parts[i]).slice(-4);
			}
			$('#name').val(parts.join('').split('').reverse().join('.') + '.ip6.arpa');
			$('a[href=#create]').tab('show');
		}
	});

	$('#changelog-expand-all').on('click', function() {
		$('table.changelog tbody tr[data-changeset]').each(function() {
			show_changes($(this), true);
		});
		$(this).hide();
		$('#changelog-collapse-all').show();
	});

	$('#changelog-collapse-all').on('click', function() {
		$('table.changelog tbody tr[data-changeset]').each(function() {
			show_changes($(this), false);
		});
		$(this).hide();
		$('#changelog-expand-all').show();
	});

	// Add row-expanding functionality on changelog table
	$('table.changelog').each(function() {
		$('tbody tr', this).each(function() {
			$('td:last-child', this).append($('<span>').addClass('glyphicon').addClass('glyphicon-chevron-right'));
		}).on('click', function() { show_changes($(this)); });
		$('tbody tr a', this).on('click', function(e) {
			e.stopPropagation();
		});

		get_changelog_from_location();
		window.onpopstate = function(event) {
			get_changelog_from_location();
		}
	});

	// Enable link to changelog entry
	function get_changelog_from_location() {
		var url = document.location.toString();
		if(url.match('#')) {
			$('tbody tr[data-changeset="' + url.split('#')[2] + '"]').each(function() {
				show_changes($(this));
				var offset = $(this).offset();
				offset.top -= 60;
				$('html, body').animate({
					scrollTop: offset.top,
				});
			});
		}
	}

	function show_changes(tr, display) {
		// display: undefined toggles, true always shows, false always hides.
		var zone = tr.data('zone');
		var changeset = tr.data('changeset');

		var hash = '#changelog#' + changeset;
		if(display !== undefined) {
			// don't do anything for #changelog-expand-all / #changelog-collapse-all
		} else if(history) {
			history.replaceState(null, null, hash);
		} else {
			window.location.hash = hash;
		}

		if (display === undefined) {
			$('td:last-child span', tr).toggleClass('glyphicon-chevron-right').toggleClass('glyphicon-chevron-down');
		} else if (display === true) {
			$('td:last-child span', tr).removeClass('glyphicon-chevron-right').addClass('glyphicon-chevron-down');
		} else {
			$('td:last-child span', tr).removeClass('glyphicon-chevron-down').addClass('glyphicon-chevron-right');
		}
		if(tr.data('details_loaded')) {
			tr.next().toggle(display);
		} else if (display !== false) {
			var newtr = $('<tr>');
			var newtd = $('<td>');
			newtd.prop('colspan', 6);
			newtr.append(newtd);
			newtr.addClass('changeset');
			tr.after(newtr);

			tr.data('details_loaded', true);

			$.getJSON('../api/v2/zones/' + encodeURIComponent(zone) + '/changes/' + encodeURIComponent(changeset), function(data) {
				var change;
				for(var i = 0, change; change = data.changes[i]; i++) {
					var panelheader = $('<div>').addClass('panel-heading');
					if(!change.before) {
						var panelclass = 'success';
						panelheader.append('Added ').append($('<tt>').append(change.after.name + ' ' + change.after.type)).append(', TTL: ' + change.after.ttl);
					} else if(!change.after) {
						var panelclass = 'danger';
						panelheader.append('Deleted ').append($('<tt>').append(change.before.name + ' ' + change.before.type)).append(', TTL: ' + change.before.ttl);
					} else {
						var panelclass = 'default';
						var tt = $('<tt>');
						show_diff(tt, change.before.name + ' ' + change.before.type, change.after.name + ' ' + change.after.type);
						panelheader.append('Updated ').append(tt).append(', TTL: ');
						show_diff(panelheader, change.before.ttl, change.after.ttl)
						var heading = 'Updated ';
					}
					var panel = $('<div>').addClass('panel').addClass('panel-' + panelclass);
					var panelbody = $('<div>').addClass('panel-body');
					var table = $('<table>').addClass('table').addClass('table-condensed');
					table.append($('<thead>').append($('<tr>')
						.append($('<th>').append('Content'))
						.append($('<th>').append('Enabled'))
					));
					var tbody = $('<tbody>');
					if(change.before) {
						for(var j = 0, rr; rr = change.before.rrs[j]; j++) {
							var tr = $('<tr>');
							var rr_match = false;
							var after_rr;
							if(change.after) {
								for(var k = 0, after_rr; after_rr = change.after.rrs[k]; k++) {
									if(after_rr.content == rr.content) {
										rr_match = true;
										change.after.rrs.splice(k, 1); // Remove from array
										break;
									}
								}
							}
							if(rr_match) {
								var td = $('<td>').addClass('content');
								show_diff(td, rr.content, after_rr.content);
								tr.append(td);
								var td = $('<td>');
								show_diff(td, rr.enabled ? 'Yes' : 'No', after_rr.enabled ? 'Yes' : 'No');
								tr.append(td);
							} else {
								tr.append($('<td>').addClass('content').append($('<del>').append(rr.content)));
								tr.append($('<td>').append($('<del>').append(rr.enabled ? 'Yes' : 'No')));
							}
							tbody.append(tr);
						}
					}
					if(change.after) {
						for(var j = 0, rr; rr = change.after.rrs[j]; j++) {
							var tr = $('<tr>');
							tr.append($('<td>').addClass('content').append($('<ins>').append(rr.content)));
							tr.append($('<td>').append($('<ins>').append(rr.enabled ? 'Yes' : 'No')));
							tbody.append(tr);
						}
					}
					table.append(tbody);
					panelbody.append(table);
					var before_comment = null;
					var after_comment = null;
					if(change.before && change.before.comment) before_comment = change.before.comment;
					if(change.after && change.after.comment) after_comment = change.after.comment;
					if(before_comment || after_comment) {
						var comment = $('<p>').append('RRSet comment: ');
						show_diff(comment, before_comment, after_comment);
						panelbody.append(comment);
					}
					panel.append(panelheader);
					panel.append(panelbody);
					newtd.append(panel);
				}
			});
		}
	}

	function show_diff(element, before, after) {
		if(!before) {
			element.append($('<ins>').append(after));
		} else if(!after) {
			element.append($('<del>').append(before));
		} else if(before == after) {
			element.append(after);
		} else {
			element.append($('<del>').append(before)).append(' ').append($('<ins>').append(after));
		}
	}

	// Add delete/restore zone confirmation checkbox
	$('form.zonedelete, form.zonerestore, form.disablednssec').each(function() {
		var form = $(this);
		$('label', form).hide();
		$('.alert', form).hide();
		$('button.btn-danger', form).on('click', function(e) {
			if($('input:checkbox:checked', form).length == 0) {
				$('label', form).show('fast');
				$('.alert', form).show('fast');
				$('button span', form).hide('fast');
				$(this).prop('disabled', true);
				e.preventDefault();
			}
		});
		$('input:checkbox', form).on('click', function(e) {
			$('button.btn-danger', form).prop('disabled', !this.checked);
		});
	});

	// Add filter functionality for zone list tables
	$('table.zonelist').each(function() {
		var table = $(this);
		$('thead tr th', table).each(function() {
			// Add filter button
			$(this).css('position', 'relative');
			var button = $('<button>');
			button.addClass('btn').addClass('btn-default').addClass('btn-xs');
			button.css('position', 'absolute').css('right', '5px').css('top', '4px');
			var span = $('<span>');
			span.addClass('glyphicon').addClass('glyphicon-filter');
			button.append(span);
			button.on('click', function(){show_filter($(this))});
			$(this).append(button);
			var input = $('<input>');
			input.type = 'text';
			input.css('position', 'absolute').css('right', '5px').css('top', '5px');
			input.css('width', '40%').css('padding', '0').css('line-height', '12px');
			input.prop('placeholder', 'Filter');
			input.hide();
			input.on('input', function(){filter()});
			input.on('blur', function(){hide_filter($(this))});
			$(this).append(input);
		});

		function show_filter(button) {
			button.hide();
			var input = button.parent().find('input');
			input.show('fast');
			input.focus();
		}

		function hide_filter(input) {
			if(input.val() == '') {
				input.hide('fast');
				var button = input.parent().find('button');
				button.show();
			}
		}

		function filter() {
			var filters = [];
			$('thead th input', table).each(function() {
				if(this.value != '') {
					filters[$(this).parent().prop('cellIndex')] = this.value.toLowerCase();
				}
			});
			$('tbody tr', table).each(function() {
				var filtered = false;
				$('td', this).each(function() {
					var cellIndex = $(this).prop('cellIndex');
					if(filters[cellIndex] && $(this).text().toLowerCase().indexOf(filters[cellIndex]) == -1) {
						filtered = true;
					}
				});
				if(filtered) {
					$(this).addClass('filtered');
				} else {
					$(this).removeClass('filtered');
				}
			});
		}
	});
});

/*
stickyHeader.jquery.js from https://github.com/kingkool68/stickyHeader
see NOTICE for licensing information
Modified to work with box-sizing: border-box (as used by Bootstrap)
*/
jQuery(document).ready(function ($) {
	var tables = $('table.stickyHeader');
	tables.each(function(i){
		var table = tables[i];
		var tableClone = $(table).clone(true).empty().removeClass('stickyHeader');
		var theadClone = $(table).find('thead').clone(true);
		var stickyHeader =  $('<div></div>').addClass('stickyHeader hidden').attr('aria-hidden', 'true');
		stickyHeader.append(tableClone).find('table').append(theadClone);
		$(table).after(stickyHeader);

		var tableHeight = $(table).height();
		var tableWidth = $(table).width() + Number($(table).css('padding-left').replace(/px/ig,"")) + Number($(table).css('padding-right').replace(/px/ig,"")) + Number($(table).css('border-left-width').replace(/px/ig,"")) + Number($(table).css('border-right-width').replace(/px/ig,""));

		var headerCells = $(table).find('thead th');
		var headerCellHeight = $(headerCells[0]).height();

		var no_fixed_support = false;
		if (stickyHeader.css('position') == "absolute") {
			no_fixed_support = true;
		}

		var stickyHeaderCells = stickyHeader.find('th');
		stickyHeader.css('width', tableWidth);
		var cellWidths = [];
		for (var i = 0, l = headerCells.length; i < l; i++) {
			// Modified line here: must add original header cell's left/right padding to the width
			cellWidths[i] = $(headerCells[i]).width() + $(headerCells[i]).css('padding-left') + $(headerCells[i]).css('padding-right');
		}
		for (var i = 0, l = headerCells.length; i < l; i++) {
			$(stickyHeaderCells[i]).css('width', cellWidths[i]);
		}

		var cutoffTop = $(table).offset().top - 51;
		var cutoffBottom = tableHeight + cutoffTop - headerCellHeight;

		$(window).scroll(function() {
		var currentPosition = $(window).scrollTop();
			if (currentPosition > cutoffTop && currentPosition < cutoffBottom) {
				stickyHeader.removeClass('hidden');
				if (no_fixed_support) {
					stickyHeader.css('top', currentPosition + 'px');
				}
			}
			else {
				stickyHeader.addClass('hidden');
			}
		});
	});
});
