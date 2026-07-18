# Checked / unchecked (reconciliation) status

Each `costs` entry carries a `checked` flag: has this entry been reconciled
against the bank statement? Grep `{CHECKEDVALUE}` to find every spot that writes
the raw value (the analogue of `{TIMEDVALUE}`).

## Values

- `0` = unchecked, `2` = checked. Toggling only ever flips `0 ⇄ 2`.
- `1` is a legacy "provisional" value — never written by the current code, though a
  reader could still distinguish it (`✓?`) from `2` (`✓`).
- **Reads treat anything non-zero as checked** (`checked > 0` / `checked != 0`), so
  a stray `1` counts as checked in balances and the frontier queries.
- `internal_fsck()` requires the column to be non-empty / non-null (`CostsDB.php`).
- Normalisation on the way in (`ItemData::from_raw`): missing/empty → `0`, then any
  truthy → `2`. The edit form's "Checked?" checkbox (`get_checkbox` → `0/1`) is
  mapped the same way.

## Write path (toggle)

1. UI: clicking the account-letter link `.bpchkd_in` inside a row →
   `BP.do_action({action:'check', id})` (`main.js`).
2. `do_action` POSTs `action=check` as JSON, shows the curtain, and on success
   **reloads the whole page** — there is no in-place DOM patch, the list simply
   re-renders with the new CSS class.
3. Server: `Control::process_action` case `check` → `Item::toggle_checked($DB,$id)`
   (`ItemData`), which reads the current value and writes `checked = cur>0 ? 0 : 2`,
   then returns `{"success":true}`.
4. Create/edit also sets it via the form checkbox (`Item::parse_html_form`;
   `$cchecked` is pre-ticked when `checked == 2`).

## Display path

- Each row gets classes `bpchkd bpchkd_<0|2>` and `bpacc_<accountletter>`
  (`Item::to_html`). The toggle target is the `.bpchkd_in` link.
- The look is **data-driven per account**: `Html::css_accounts()` emits CSS from
  `accountnames.checkedcss` (applied to `.bpchkd .bpchkd_in`, i.e. always) and
  `accountnames.checkednotcss` (applied to `.bpchkd_0 .bpchkd_in`, i.e. only when
  unchecked). So each account can style checked vs unchecked differently.

## First-unchecked frontier (`FirstChecked`)

Purpose: mark, per account, where reconciliation got to — the earliest entry still
unchecked. `init()` iterates the distinct `accountto` values and, for each:

- finds the **first (earliest) unchecked** entry (`checked=0`, ascending, limit 1).
  If present it produces (a) an HTML line for the modal via the `firstchecked_item`
  template label, and (b) a JS line via `firstchecked_jsitem` that does
  `jQuery('.bpid_<id>').addClass('bpfirst_unc')` to highlight that row;
- finds the **latest checked** entry (before that first-unchecked one, or overall
  if everything is checked) and stores it in `data[unixday]` as `"<acc> (checked)"`.

Outputs:

- `gethtml()` → `$firstcheckedhtml`, the body of the "First unchecked entries"
  modal (opened by the "Unchecked" button).
- `getjs()` → `$firstcheckedjs`, the highlighting script injected into the list
  page.
- `forday($ud)` → the "(checked)" annotation for the timeline chart, so the chart
  marks each account's last-checked point.

`init()` is invoked before rendering the list / search / recently-modified /
timeline views (from the deployment wrapper). It is not memoised, but it is only a
couple of indexed lookups per account.

## Checked balance in the account summary

The account-summary `+` flag (`summaryconfig`, see `Summary`) adds a second figure
for an account: the sum of entries with `checked > 0` (`accountto` minus
`accountfrom`), rendered via the `summary_account_checked` label as a muted
"Checked balance". It lets you compare the reconciled balance (what the bank should
show) against the full balance.
