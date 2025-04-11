# Copypress-Rest-API
The **COPYPRESS REST API** plugin extends the WordPress REST API with custom endpoints for managing posts, categories, tags, post types, and taxonomies.

== Installation ==
1. Download the plugin files.
2. Upload the plugin folder to the `/wp-content/plugins/` directory.
3. Activate the plugin from the WordPress admin panel.

### Endpoints
1. `/copypress-api/v1/login` - Login with username and password to get a JWT token.
   - Request Body:
   - `username` (string): WordPress username
   - `password` (string): WordPress password

2. `POST /copypress-api/v1/posts` - Create a new post.
   - Request Body: 
     - `title` (string): The title of the post.
     - `content` (string): The content of the post.
     - `excerpt` (string, optional): The excerpt of the post.
     - `category` (int, optional): The category ID of the post.
     - `tags` (string, optional): Comma-separated list of tag slugs.
     - `image` (string, optional): URL of an image to be set as the post's featured image.
     - `post_type` (string, optional): The post type (default: `post`).
     - `author_id` (int, optional): The ID of the post author (default: current user).
     - `post_status` (string): Post publish status.
   - Response: Success message, HTTP status code, and created post object.

3. `PUT /copypress-api/v1/posts/{id}` - Update an existing post.
   - Request Body: Same as `POST` endpoint.
   - Response: Success message, HTTP status code, and updated post object.

4. `DELETE /copypress-api/v1/posts/{id}` - Delete a post.
   - Response: Success message and HTTP status code.

5. `GET /copypress-api/v1/categories` - Get all categories.
   - Response: Category ID, name, and slug.

6. `GET /copypress-api/v1/tags` - Get all tags.
   - Response: Tag ID, name, and slug.

7. `GET /copypress-api/v1/post-types` - Get all public post types.
   - Response: Post type name and label.

8. `GET /copypress-api/v1/get-taxonomies/{post_type}` - Get all taxonomies (categories and tags) associated with a specific post type.
   - Response: List of categories (hierarchical taxonomies) and tags (non-hierarchical taxonomies).

== Changelog ==
= 1.2 =
* Role-based permissions for Administrator, Editor, and Contributor to publish content have been added.

= 1.1 =
* added permission check.
* added login with token solution.
* removed api-key based functionality.

== Changelog ==
= 1.0 =
* Initial release with functionality for post creation, update, deletion, and fetching categories, tags, post types, and taxonomies.
