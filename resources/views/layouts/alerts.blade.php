@if ($errors->any())
    <div class="yativo_alert yativo_alert-danger yativo_alert-dismissible">
        <strong class="yativo_alert-title">There were some problems with your input:</strong>
        <ul class="yativo_alert-list">
            @foreach ($errors->all() as $error)
                <li class="yativo_alert-item">{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="yativo_alert-close" aria-label="Close">
            <span aria-hidden="true">×</span>
        </button>
    </div>
@endif


<style>
    /* General Alert Styling */
    .yativo_alert {
        background-color: #f8d7da;
        /* Red background for errors */
        color: #721c24;
        /* Dark red text */
        border-radius: 5px;
        padding: 15px;
        position: relative;
        font-family: Arial, sans-serif;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 15px;
    }

    /* Dismissable Alert (with close button) */
    .yativo_alert-dismissible {
        padding-right: 40px;
        /* Space for close button */
    }

    .yativo_alert-title {
        font-weight: bold;
        margin-bottom: 10px;
    }

    .yativo_alert-list {
        list-style-type: none;
        padding-left: 0;
        margin: 0;
    }

    .yativo_alert-item {
        margin-bottom: 5px;
    }

    .yativo_alert-close {
        position: absolute;
        top: 10px;
        right: 10px;
        border: none;
        background: transparent;
        font-size: 18px;
        cursor: pointer;
        color: #721c24;
    }

    .yativo_alert-close:hover {
        color: #f44336;
        /* Color change on hover */
    }

    /* For accessibility - Close button when focused */
    .yativo_alert-close:focus {
        outline: none;
        box-shadow: 0 0 0 2px #f44336;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const closeButtons = document.querySelectorAll('.yativo_alert-close');

        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const alert = button.closest('.yativo_alert');
                if (alert) {
                    alert.style.display = 'none'; // Hide the alert on click
                }
            });
        });
    });
</script>
