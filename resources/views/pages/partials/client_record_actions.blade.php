<details class="client-record-actions">
    <summary aria-label="Client actions">&#8942;</summary>
    <div class="client-record-actions-menu">
        <a href="?client_id={{ $client['id'] }}{{ $showArchived ? '&view=archived' : '' }}">View Bills</a>
        @if ($showArchived)
        <form method="POST" action="?view=archived"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="client_action" value="restore"><input type="hidden" name="client_id" value="{{ $client['id'] }}"><button type="submit">Restore Active</button></form>
        @else
        <form method="POST" action=""><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="client_action" value="set_status"><input type="hidden" name="client_id" value="{{ $client['id'] }}"><input type="hidden" name="status" value="active"><button type="submit">Set Active</button></form>
        <form method="POST" action=""><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="client_action" value="set_status"><input type="hidden" name="client_id" value="{{ $client['id'] }}"><input type="hidden" name="status" value="inactive"><button type="submit">Set Inactive</button></form>
        <form method="POST" action=""><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="client_action" value="set_status"><input type="hidden" name="client_id" value="{{ $client['id'] }}"><input type="hidden" name="status" value="suspended"><button type="submit">Suspend</button></form>
        <form method="POST" action=""><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="client_action" value="archive"><input type="hidden" name="client_id" value="{{ $client['id'] }}"><button type="submit" class="danger" onclick="return confirm('Archive this client?')">Archive</button></form>
        @endif
    </div>
</details>
