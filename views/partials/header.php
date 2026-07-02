<?php declare(strict_types=1); ?>
<header class="border-bottom bg-white">
    <nav class="navbar navbar-expand-lg container">
        <a class="navbar-brand fw-semibold" href="<?= e(base_url('/')) ?>">
            All The Venues
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navMain" aria-controls="navMain"
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(base_url('venues')) ?>">Browse Venues</a>
                </li>
            </ul>
        </div>
    </nav>
</header>
