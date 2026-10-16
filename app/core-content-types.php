<?php

// Core's built-in content types. Plugins and themes add their own with register_content_type().

register_content_type('seasons', ['label' => 'Seasons', 'singularLabel' => 'Season']);
register_content_type('productions', ['label' => 'Productions', 'singularLabel' => 'Production']);
register_content_type('people', ['label' => 'People', 'singularLabel' => 'Person']);
register_content_type('works', ['label' => 'Works', 'singularLabel' => 'Work']);
register_content_type('posts', ['label' => 'Posts', 'singularLabel' => 'Post']);
