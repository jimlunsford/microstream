# Microstream Protocol v1.0

A minimal JSON over HTTPS protocol for small PHP microblog sites that can run on shared hosting and talk to each other.

- Version: `microstream-1.0`
- Status: Draft
- Target stack: PHP 8 plus MySQL or compatible database

This document defines how Microstream nodes discover each other, exchange public posts, and send events such as follows and mentions.

---

## Table of contents

1. Goals  
2. Transport and discovery  
   2.1 Transport  
   2.2 API base  
   2.3 HTML discovery  
3. Authentication between nodes  
   3.1 Node secret  
   3.2 Auth header  
   3.3 Trust model  
4. Common JSON structure  
5. Core data types  
   5.1 Node object  
   5.2 User object  
   5.3 Post object  
   5.4 Event object  
6. Endpoints  
   6.1 `GET ?route=node`  
   6.2 `GET ?route=feed`  
   6.3 `POST ?route=inbox`  
   6.4 Optional `GET ?route=user`  
7. Event types  
   7.1 `follow`  
   7.2 `unfollow`  
   7.3 `mention`  
   7.4 `reply`  
   7.5 `like`  
8. Federation workflows  
   8.1 Discovering a node  
   8.2 Following a remote user  
   8.3 Pulling remote timelines  
   8.4 Handling mentions  
9. Error handling  
10. Limits and recommendations  
11. Versioning  

---

## 1. Goals

Microstream aims to:

- Allow independent installs of a lightweight PHP microblog app to form a network  
- Work reliably on cheap shared hosting  
- Require only:
  - PHP  
  - A database (MySQL or similar)  
  - HTTPS  
- Avoid special server features such as `/.well-known` routes or long running daemons  
- Keep the spec small and readable  

The protocol is pull based by default, with optional push style notifications for events such as follows and mentions.

---

## 2. Transport and discovery

### 2.1 Transport

- All requests use HTTPS  
- Methods: `GET` and `POST`  
- Encoding: UTF 8  
- JSON content type:

  ```http
  Content-Type: application/json; charset=utf-8
  ```

### 2.2 API base

Each node exposes a single API entry point. Recommended default:

- `https://example.com/api.php`

Endpoints are selected with a `route` query parameter:

- `GET https://example.com/api.php?route=node`  
- `GET https://example.com/api.php?route=feed`  
- `POST https://example.com/api.php?route=inbox`  

Installations may choose a different path, but this layout works well on shared hosting.

### 2.3 HTML discovery

Every node should expose API entry points in HTML using `<link>` tags in the `<head>`.

Recommended:

```html
<link rel="microstream-node" href="https://example.com/api.php?route=node">
<link rel="microstream-feed" href="https://example.com/api.php?route=feed">
```

Discovery flow:

1. Fetch the home page HTML  
2. Search for `rel="microstream-node"` or `rel="microstream-feed"`  
3. Use the `href` as the node or feed endpoint  

If the link is missing, clients may try `https://example.com/api.php?route=node` directly.

---

## 3. Authentication between nodes

### 3.1 Node secret

Each node generates a shared secret on install, for example:

```php
NODE_ID     = "6d6a9c5e-7a2a-4e49-9ce0-79d231ea6ca4";
NODE_SECRET = "random-long-string";
```

- `NODE_ID` is public, usually a UUID  
- `NODE_SECRET` is private and must not be exposed publicly  

### 3.2 Auth header

Authenticated node to node requests must include both headers:

```http
X-Microstream-Node: <sender-node-id>
X-Microstream-Key: <sender-node-secret>
```

The receiving node:

1. Looks up the sender by `NODE_ID`  
2. Confirms that `X-Microstream-Key` matches the expected secret  
3. Rejects the request with `401` or `403` if it does not match  

### 3.3 Trust model

For v1, trust is configured manually:

- An admin adds a remote node in the UI  
- They enter:
  - Remote base URL  
  - Remote `NODE_ID`  
  - Remote shared key, typically exchanged out of band  

Public endpoints such as `feed` may be open. Inbox endpoints should require authentication to avoid abuse.

---

## 4. Common JSON structure

Every JSON response that participates in the protocol must include a `protocol` field that identifies the version.

Top level format:

```json
{
  "protocol": "microstream-1.0",
  "data": {
    "...": "..."
  }
}
```

For brevity, examples in this document often omit the `data` wrapper, but implementations should keep the `protocol` field at the top level.

---

## 5. Core data types

### 5.1 Node object

Represents a Microstream node.

```json
{
  "node_id": "6d6a9c5e-7a2a-4e49-9ce0-79d231ea6ca4",
  "title": "Jim's Stream",
  "url": "https://example.com",
  "api_base": "https://example.com/api.php",
  "software": {
    "name": "microstream",
    "version": "1.0.0"
  }
}
```

### 5.2 User object

Represents a local user that can create posts.

```json
{
  "username": "jim",
  "display_name": "Jim Lunsford",
  "url": "https://example.com/@jim",
  "avatar_url": "https://example.com/media/avatar-jim.jpg"
}
```

### 5.3 Post object

Represents a microblog post.

```json
{
  "id": "https://example.com/p/1234",
  "local_id": 1234,
  "author": {
    "username": "jim",
    "display_name": "Jim Lunsford",
    "url": "https://example.com/@jim"
  },
  "url": "https://example.com/p/1234",
  "content_html": "<p>This is my note.</p>",
  "content_text": "This is my note.",
  "created_at": "2025-12-05T15:42:00Z",
  "in_reply_to": null,
  "visibility": "public"
}
```

Rules:

- `id` must be a globally unique URL  
- `created_at` must be an ISO 8601 timestamp with `Z` suffix for UTC  
- `content_html` should be sanitized HTML  
- `content_text` is a plain text version for indexing or simple clients  

### 5.4 Event object

Used in push style inbox messages.

```json
{
  "type": "mention",
  "from_node": "https://remote.com",
  "from_node_id": "abc123-uuid",
  "from_user": "alex",
  "to_user": "jim",
  "post_id": "https://remote.com/p/777",
  "snippet": "Hey @jim, this made me think of you",
  "created_at": "2025-12-05T16:10:00Z"
}
```

Allowed `type` values in v1:

- `follow`  
- `unfollow`  
- `mention`  
- `reply`  
- `like`  

Each type has its own required fields, described in section 7.

---

## 6. Endpoints

All endpoints below assume the default API base:

- `https://example.com/api.php`

### 6.1 `GET ?route=node`

Returns basic information about a node.

**Request**

```http
GET /api.php?route=node HTTP/1.1
Host: example.com
Accept: application/json
```

**Response**

```json
{
  "protocol": "microstream-1.0",
  "node": {
    "node_id": "6d6a9c5e-7a2a-4e49-9ce0-79d231ea6ca4",
    "title": "Jim's Stream",
    "url": "https://example.com",
    "api_base": "https://example.com/api.php",
    "software": {
      "name": "microstream",
      "version": "1.0.0"
    }
  }
}
```

Authentication: not required.

---

### 6.2 `GET ?route=feed`

Returns a public timeline of posts. This is the primary pull based endpoint.

Query parameters:

- `since` (optional)  
  ISO 8601 timestamp. Only return posts created after this time.  
- `limit` (optional)  
  Maximum number of posts to return. Recommended default 20, maximum 100.  
- `user` (optional)  
  Local username to filter by. If present, return only posts from that user.  

**Request**

```http
GET /api.php?route=feed&limit=20 HTTP/1.1
Host: example.com
Accept: application/json
```

**Response**

```json
{
  "protocol": "microstream-1.0",
  "node": {
    "node_id": "6d6a9c5e-7a2a-4e49-9ce0-79d231ea6ca4",
    "title": "Jim's Stream",
    "url": "https://example.com"
  },
  "posts": [
    {
      "id": "https://example.com/p/1234",
      "local_id": 1234,
      "author": {
        "username": "jim",
        "display_name": "Jim Lunsford",
        "url": "https://example.com/@jim"
      },
      "url": "https://example.com/p/1234",
      "content_html": "<p>Short post here</p>",
      "content_text": "Short post here",
      "created_at": "2025-12-05T15:42:00Z",
      "in_reply_to": null,
      "visibility": "public"
    }
  ]
}
```

Authentication: may be open or protected, but for v1 this should be public to allow easy federation.

---

### 6.3 `POST ?route=inbox`

Receives events from other nodes, such as follows and mentions.

**Request**

```http
POST /api.php?route=inbox HTTP/1.1
Host: example.com
Content-Type: application/json
X-Microstream-Node: abc123-uuid
X-Microstream-Key: remote-secret

{
  "protocol": "microstream-1.0",
  "event": {
    "type": "mention",
    "from_node": "https://remote.com",
    "from_node_id": "abc123-uuid",
    "from_user": "alex",
    "to_user": "jim",
    "post_id": "https://remote.com/p/777",
    "snippet": "Hey @jim, this made me think of you",
    "created_at": "2025-12-05T16:10:00Z"
  }
}
```

**Response, success**

```json
{
  "protocol": "microstream-1.0",
  "status": "ok"
}
```

**Response, failure**

```json
{
  "protocol": "microstream-1.0",
  "status": "error",
  "error": {
    "code": "unauthorized",
    "message": "Invalid node key"
  }
}
```

Authentication: required. Requests without valid `X-Microstream-Node` and `X-Microstream-Key` should be rejected.

---

### 6.4 Optional `GET ?route=user`

Looks up public information about a local user.

**Request**

```http
GET /api.php?route=user&username=jim HTTP/1.1
Host: example.com
Accept: application/json
```

**Response**

```json
{
  "protocol": "microstream-1.0",
  "user": {
    "username": "jim",
    "display_name": "Jim Lunsford",
    "url": "https://example.com/@jim",
    "avatar_url": "https://example.com/media/avatar-jim.jpg"
  }
}
```

Authentication: not required.

---

## 7. Event types

All inbox events are wrapped in an `event` object inside the request body.

```json
{
  "protocol": "microstream-1.0",
  "event": {
    "type": "...",
    "...": "..."
  }
}
```

### 7.1 `follow`

Indicates that a remote user wants to follow a local user.

```json
{
  "type": "follow",
  "from_node": "https://remote.com",
  "from_node_id": "abc123-uuid",
  "from_user": "alex",
  "to_user": "jim",
  "created_at": "2025-12-05T16:10:00Z"
}
```

Recommended server behavior:

- Record this follower  
- Optionally notify the local user  
- Optionally require approval  

### 7.2 `unfollow`

Indicates that a remote user has stopped following a local user.

```json
{
  "type": "unfollow",
  "from_node": "https://remote.com",
  "from_node_id": "abc123-uuid",
  "from_user": "alex",
  "to_user": "jim",
  "created_at": "2025-12-05T16:10:00Z"
}
```

Recommended server behavior:

- Remove or deactivate this follower if present  

### 7.3 `mention`

Indicates that a remote post mentioned a local user.

```json
{
  "type": "mention",
  "from_node": "https://remote.com",
  "from_node_id": "abc123-uuid",
  "from_user": "alex",
  "to_user": "jim",
  "post_id": "https://remote.com/p/777",
  "snippet": "Hey @jim, this made me think of you",
  "created_at": "2025-12-05T16:10:00Z"
}
```

Recommended server behavior:

- Store the mention  
- Display it in a mentions view  
- Optionally fetch the full post from the remote node feed  

### 7.4 `reply`

Indicates that a remote post is a reply to a local post, or to a remote post visible on this node.

```json
{
  "type": "reply",
  "from_node": "https://remote.com",
  "from_node_id": "abc123-uuid",
  "from_user": "alex",
  "parent_post_id": "https://example.com/p/1234",
  "post_id": "https://remote.com/p/888",
  "snippet": "Here is my response",
  "created_at": "2025-12-05T16:10:00Z"
}
```

Recommended server behavior:

- Store information that this remote post is a reply to the parent  
- Optionally fetch the full reply post  

### 7.5 `like`

Records that a remote user liked a post.

```json
{
  "type": "like",
  "from_node": "https://remote.com",
  "from_node_id": "abc123-uuid",
  "from_user": "alex",
  "post_id": "https://example.com/p/1234",
  "created_at": "2025-12-05T16:10:00Z"
}
```

Recommended server behavior:

- Increment like count for the referenced post, or maintain a list of likes  
- Optionally ignore likes if the local configuration does not support them  

---

## 8. Federation workflows

### 8.1 Discovering a node

Given a site URL such as `https://example.com`:

1. Fetch `https://example.com/`  
2. Parse HTML, search for `<link rel="microstream-node">`  
3. Use the `href` as the node endpoint, for example `https://example.com/api.php?route=node`  
4. Fetch that URL to retrieve node information  

If the link is missing, clients may try `https://example.com/api.php?route=node` directly.

---

### 8.2 Following a remote user

Local node L wants to follow user `alex` on remote node R.

1. User on L enters `https://remote.com/@alex` or remote base URL  
2. L discovers R’s node endpoint as described above  
3. L stores R’s `node_id`, `url`, and `api_base`  
4. L sends an authenticated `follow` event to R’s inbox  
5. R processes the event and records that `alex` is followed by a user at L, depending on R’s local policies  
6. L periodically calls R’s `feed` endpoint with `user=alex` and `since=<last-sync>` to pull posts  

---

### 8.3 Pulling remote timelines

For each remote user followed:

1. Local node L sends:

   ```http
   GET https://remote.com/api.php?route=feed&user=alex&since=<last-sync>&limit=50
   ```

2. L receives an array of post objects  
3. L stores them in its `remote_posts` table  
4. L renders them in local timelines  

This can be triggered by:

- A cron job on the server, or  
- A simulated cron based on incoming web requests  

---

### 8.4 Handling mentions

When a user on R posts and mentions a user on L:

1. R sends a `mention` event to L’s inbox  
2. L validates authentication  
3. L stores the mention event  
4. Optional: L fetches the full remote post from R’s feed  

---

## 9. Error handling

Servers should use standard HTTP status codes:

- `200` for success  
- `400` for invalid input  
- `401` for missing authentication  
- `403` for invalid or unauthorized node key  
- `404` for unknown routes or resources  
- `429` for rate limiting  
- `500` for unexpected server errors  

JSON error body format:

```json
{
  "protocol": "microstream-1.0",
  "status": "error",
  "error": {
    "code": "invalid_request",
    "message": "The 'type' field is required"
  }
}
```

---

## 10. Limits and recommendations

- `limit` on feeds should default to 20 and must not exceed 100  
- Servers should implement simple rate limiting per remote node  
- Servers should sanitize all HTML, and never trust incoming `content_html` blindly  
- Servers should validate URLs, especially for fields such as `post_id`, before storing or using them  

---

## 11. Versioning

- `protocol` field must be `microstream-1.0` for this version  
- Future versions should change this value, for example `microstream-1.1`  
- Servers may reject unknown protocol versions, or treat them as compatible if changes are known to be backward compatible  

For changes that are not backward compatible, a new major protocol identifier should be defined instead of reusing `microstream-1.0`.
