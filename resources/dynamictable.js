/**
 * This file is part of the MediaWiki extension PageProperties.
 *
 * PageProperties is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * PageProperties is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PageProperties.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 * @ingroup extensions
 * @author thomas-topway-it <thomas.topway.it@mail.com>
 * @copyright Copyright © 2021-2022, https://wikisphere.org
 */

(function () {
	function increment_name(value, increment) {
		if (!value) {
			return "";
		}

		var num = parseInt(value.match(/\d+/)) + increment;

		if (isNaN(num)) {
			return value + "_" + increment;
		}

		return value.replace(/\d+/, num);
	}

	var OoUiDropdownInputWidget_options = [];
	var OoUiComboBoxInputWidget_options = [];

	$(".pageproperties_dynamictable_add_button").click(function () {

		var $table = $(this).closest(".pageproperties_dynamictable table");

		// search table

		if (!$table.length) {
			$(this)
				.parents()
				.each(function () {
					$table = $(this).has(".pageproperties_dynamictable").first();

					if ($table.length) {
						$tr_first = $($table).find("tr:first");
						$tr_last = $($table).find("tr:last");
						return false;
					}
				});

		} else {
			var $tr_first = $(this).closest("tr:first");
			var $tr_last = $(this).closest("tr:last");

		}

		if (!$table.length) {
			console.log("cannot find related pageproperties_dynamictable");
			return;
		}

		// clone latest row

		var $clone = $tr_last.clone(true, true);

		for (var attr of ["name", "id"]) {
			$($clone)
				.find("[" + attr + "]")
				.each(function () {
					if ($(this).prop(attr)) {
						$(this).attr(attr, increment_name($(this).prop(attr), 1));
					}
				});
		}

		// left cell

		var cell_first = $($tr_first).find("td.pageproperties_dynamictable_key_cell").eq(0);
		var cell_last = $($tr_last).find("td.pageproperties_dynamictable_key_cell").eq(0);


		//var row_index = $tr_last.index();


		$(cell_first)
			.find("[data-ooui]")
			.each(function () {

				//	if($(this).hasClass( 'oo-ui-widget' )) {
				var id = $(this).attr("id");
				var el = $("#" + id);
				var ooui_el = OO.ui.infuse(el);
				//console.log(ooui_el.constructor.name);
				//console.log(ooui_el);

				switch (ooui_el.constructor.name) {
					case "OoUiComboBoxInputWidget":

						if (!OoUiComboBoxInputWidget_options.length) {
							var menu_items = ooui_el.menu.items; // ooui_el.getMenu()

							for (var menu_item of menu_items) {
								if (menu_item.constructor.name == "OoUiMenuOptionWidget") {
									OoUiComboBoxInputWidget_options.push({
										label: menu_item.label,
										data: menu_item.data,
									});
								} else if (
									menu_item.constructor.name == "OoUiMenuSectionOptionWidget"
								) {
									OoUiComboBoxInputWidget_options.push({
										optgroup: menu_item.label,
									});
								}
							}
						}


						// element with name "wp{$params['fieldname']}"
						var input_el = $(cell_last).find("input[type=text]");

						var new_element = new OO.ui.ComboBoxInputWidget({
							name: increment_name(input_el.prop("name"), 1),
							options: OoUiComboBoxInputWidget_options,
						});

						$($clone)
							.find(".pageproperties_dynamictable_key_cell")
							.eq(0)
							.html(new_element.$element);

						break;

					case "OoUiDropdownInputWidget":
						if (!OoUiDropdownInputWidget_options.length) {
							var optgroup = null;

							$($tr_first)
								.find("option")
								.each(function () {
									var optgroup_ = $(this).closest("optgroup").attr("label");

									if (optgroup_ != optgroup) {
										optgroup = optgroup_;
										OoUiDropdownInputWidget_options.push({ optgroup: optgroup });
									}

									OoUiDropdownInputWidget_options.push({
										label: $(this).text(),
										data: $(this).val(),
									});
								});
						}

						// element with name "wp{$params['fieldname']}"
						var input_el = $(cell_last).find("select");

						var new_element = new OO.ui.DropdownInputWidget({
							options: OoUiDropdownInputWidget_options,
							name: increment_name(input_el.prop("name"), 1),
						});


						$($clone)
							.find(".pageproperties_dynamictable_key_cell")
							.eq(0)
							.html(new_element.$element);

						break;
				}
			});



		// right cell

		//var found_text_input = $($tr).find("input[type=text]");

		var cell = $($tr_last).find("td.pageproperties_dynamictable_value_cell").eq(0);

		var input_el = $(cell).find("input[type=text]");

		var textInput = new OO.ui.TextInputWidget({
			value: "",
			name: increment_name(input_el.prop("name"), 1),
			//id: increment_name(input_el.prop("id"),2),
		});

		$($clone)
			.find(".pageproperties_dynamictable_value_cell")
			.html(textInput.$element);

		$tr_last.after($clone);
	});

	$(".pageproperties_dynamictable_cancel_button").click(function () {
		if ($(this).closest("table").find("tr").length > 1) {
			$(this).closest("tr").remove();
		} else {
			$(this).closest("table").find("tr").find(":text").val("");
		}
	});
})();