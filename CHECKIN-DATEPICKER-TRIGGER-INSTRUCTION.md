# How to Programmatically Open the MotoPress Hotel Booking Check-In Datepicker

## Context

The MotoPress Hotel Booking plugin uses **kbwood's jQuery Datepick v5.0.1** for its date pickers. The check-in input field is a `<input type="text">` with class `mphb-datepick` and an ID matching the pattern `mphb_check_in_date-{uniqid}`. When a user clicks this input, the browser fires a `focus` event, which the datepick plugin listens to and responds by opening the calendar popup.

## What You Need to Call From an External Button

To open the check-in datepicker from a button (or any element) that lives **outside** the search form, use this single jQuery call:

```js
jQuery('.mphb_sc_search-form .mphb-datepick[id^="mphb_check_in_date"]').datepick('show');
```

That's it. This is the plugin's public API method. It does exactly what a click/focus on the input does:

1. Hides any currently open datepicker
2. Reads the current value from the input (if any)
3. Generates the calendar HTML
4. Positions the popup below the input
5. Fires the `onShow` callback (which loads availability data via AJAX)
6. Displays the calendar

## Important Details

- **jQuery is required** and must be loaded before this call.
- **The datepick plugin must already be initialized** on the input. This happens automatically on DOM ready via the `mphb.js` script, so as long as the search form is rendered on the page and scripts have loaded, the input is ready.
- If there are **multiple search forms** on the page, each has a unique `{uniqid}` suffix. You can target a specific one by ID: `jQuery('#mphb_check_in_date-XXXX').datepick('show');`
- If you only want to use `.focus()` instead (which also works since `showOnFocus` is `true` by default): `jQuery('.mphb_sc_search-form .mphb-datepick[id^="mphb_check_in_date"]').focus();` — however, `.datepick('show')` is more explicit and reliable.

## Example: Button Click Handler

```js
jQuery(document).ready(function($) {
  $('#my-external-check-availability-button').on('click', function(e) {
    e.preventDefault();
    $('.mphb_sc_search-form .mphb-datepick[id^="mphb_check_in_date"]').datepick('show');
  });
});
```

## Selector Breakdown

| Selector Part | Purpose |
|---|---|
| `.mphb_sc_search-form` | The search form container (shortcode variant) |
| `.mphb-datepick` | Class shared by all datepicker inputs in the plugin |
| `[id^="mphb_check_in_date"]` | Targets specifically the check-in input (not check-out) |

## What Happens After Check-In Date Is Selected

After the user picks a check-in date, the Shaped theme's custom `onSelect` handler automatically:
1. Clears any existing check-out date value
2. Opens the check-out datepicker after a 150ms delay

So you only need to trigger the check-in picker — the check-out picker will chain-open automatically.
