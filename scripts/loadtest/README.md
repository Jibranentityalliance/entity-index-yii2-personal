# API load test

`api.js` is a [k6](https://k6.io) script that sends a realistic mix of API v1 requests and ramps up the number of simultaneous
clients until the server slows down or starts failing. The SFTP deploy never uploads `scripts/`.

## Install k6 (Fedora, once)

```bash
sudo dnf install https://dl.k6.io/rpm/repo.rpm
sudo dnf install k6
```

## API key

Use an API key from an **admin** account: admins have no per-minute or monthly limits. A Business key stops at 120 requests a minute,
so the test would only measure the rate limit. Every request is counted in that account's API usage like a real one.

## Run

Smoke test first, 2 clients for 1 minute, to check the key and the URLs:

```bash
k6 run -e API_KEY=eik_xxx scripts/loadtest/api.js
```

Then the ramp: 10, 25, 50, 100 and 200 simultaneous clients, 3 minutes each, about 17 minutes in total:

```bash
k6 run -e API_KEY=eik_xxx -e PROFILE=ramp scripts/loadtest/api.js
```

The run stops on its own once more than 1% of requests fail or the 95th-percentile response time passes 2 seconds, so it does not keep
hammering a struggling server. Ctrl+C stops it at any time.

| Option | Default | Meaning |
|---|---|---|
| `BASE_URL` | `http://18.213.161.69` | Server to test. |
| `API_KEY` | none, required | Admin API key. |
| `PROFILE` | `smoke` | `smoke` or `ramp`. |
| `STAGES` | `10,25,50,100,200` | Simultaneous clients at each step of the ramp. |
| `STEP` | `3m` | How long each step holds. |
| `P95_MS` | `2000` | 95th-percentile response time, in milliseconds, at which the run stops. |
| `PAUSE` | `0.5` | Average seconds each client waits between requests. `0` sends requests back to back. |

Each client sends about 1 / (`PAUSE` + response time) requests a second, so 100 clients with the default pause is up to about 200 requests a second.

## Request mix

| Name | Share | Request |
|---|---|---|
| `list` | 15% | Newest companies, 25 per page |
| `list_state` | 15% | One state, newest first, 50 per page |
| `list_state_type` | 12% | One state and entity type, 50 per page |
| `search` | 12% | Keyword search over all companies, 25 per page |
| `search_state` | 10% | Keyword search in one state |
| `page2` | 6% | Second page of a state list (cursor) |
| `view` | 15% | One company by id |
| `filing` | 15% | One company by state and filing number |

The ids and filing numbers come from real companies fetched at the start of the run.

## Reading the result

k6 prints the response times per request name (`http_req_duration{name:search}` and so on), the failure rate and the requests per second.
`rate_limited` above 0 means the key has limits. The step where the 95th percentile or the failure rate jumps is roughly the server's capacity.

Watch the instance in the Lightsail console during the ramp: CPU utilization and **CPU burst capacity**. If the burst capacity runs out,
the CPU drops to its baseline and every request slows down, so a short test can look better than a long busy day.

Run it when the live site is quiet: the database, the mirror and the webhook jobs share the same server.
