# ScamSpotter Business — billing plan

## Recommendation

Keep the current pilot free and provision it manually. After the first design partners confirm that the Outlook workflow is valuable, use **Stripe Billing** as the first payment provider:

- hosted Stripe Checkout for starting a subscription;
- Stripe Customer Portal for payment method, invoices, plan changes and cancellation;
- recurring prices in Stripe for Starter, Team and Business, each monthly and annual;
- server-side webhooks as the only source of truth for access and plan status.

This keeps card and SEPA Direct Debit support, avoids storing payment details in ScamSpotter and leaves room for international customers and a future API meter. iDEAL can be used to establish a SEPA mandate, but recurring collection should be designed around the resulting SEPA Direct Debit mandate rather than treating iDEAL as a recurring card-like payment method.

Mollie remains a valid Dutch alternative when iDEAL-first checkout or local payment operations outweigh Stripe’s subscription tooling. It should not be added alongside Stripe in the first release: two billing systems create duplicate customer, webhook, VAT and support flows.

## Account and subscription mapping

The existing `commercial_accounts` and `account_subscriptions` tables are the correct internal boundary. Add only when billing is approved:

- `billing_provider` (`stripe` or later `mollie`);
- provider customer ID and subscription ID (already represented where applicable);
- provider price ID, currency, billing interval and current period end;
- a unique `billing_events.provider_event_id` for idempotent webhook processing;
- received/processed timestamps, payload hash, processing status and error message for operational replay.

Never grant access from a Checkout success URL alone. The webhook must map the provider customer/subscription to the internal organization, validate the signature, check the expected price, and update the subscription transactionally.

## Lifecycle

1. Admin creates or approves a commercial organization.
2. Organization owner selects a plan; the server creates a hosted Checkout session with the internal account ID in provider metadata.
3. `checkout.session.completed` links the provider customer and subscription, but access is still confirmed from the subscription state.
4. Subscription created/updated/deleted and invoice paid/failed events update `account_subscriptions` and create an audit event.
5. A payment failure enters a short grace period, not an immediate data deletion. After the configured grace period, new Business checks are blocked while read-only export and billing management remain available.
6. Cancellation is normally scheduled at period end. Organization data follows the organization retention/deletion policy, not the payment provider’s retention policy.

## Pricing model

Start with a flat organization subscription plus an active-user band and fair-use monthly checks:

| Hypothesis | Users | Included checks | Monthly price |
|---|---:|---:|---:|
| Starter | up to 25 | 250 | €39 |
| Team | up to 100 | 1,000 | €79 |
| Business | up to 250 | 3,000 | €149 |

These are pilot hypotheses, not promises. Do not expose overage billing until real usage and AI cost data justify it. API and future Protect usage should have separate quotas and pricing rather than silently consuming an employee plan.

Offer annual billing only after the monthly flow is stable. A sensible experiment is 10–15% annual discount, with the plan and limit clearly shown before checkout.

## VAT and invoicing

- Show prices as “excl. btw” for business checkout and display the legal entity, invoice address and VAT number fields.
- Validate EU VAT numbers and apply the correct Dutch/EU B2B treatment with the accountant before launch.
- Use provider-hosted invoices only after the legal entity, invoice numbering, tax configuration and credit-note process have been verified.
- Store only the provider invoice ID, amount, currency, tax summary and status needed for the customer record and audit trail; do not copy payment credentials.

## Security and operations

- Keep secret keys and webhook secrets only in the server environment.
- Verify webhook signatures, use an idempotency key/event table and return quickly before asynchronous reconciliation.
- Do not log raw webhook payloads indefinitely; redact customer data and retain a short troubleshooting window.
- Add a daily reconciliation command that compares active internal subscriptions with the provider, without changing a customer silently.
- Test trial, upgrade, downgrade, cancellation, failed payment, refund, duplicate webhook and provider outage paths in test mode before live payments.

## Rollout decision

No Stripe or Mollie credentials, checkout button or live billing route is enabled in the current pilot. The Business plan/usage layer can therefore be tested without financial or VAT consequences. Activate billing only after:

1. at least 5 design partners have completed the manual workflow;
2. one or more organizations agree to pay for the workflow, not only the analysis result;
3. retention, support and AI cost data support the selected limits;
4. privacy/DPA, terms, VAT and refund/cancellation wording are approved.
