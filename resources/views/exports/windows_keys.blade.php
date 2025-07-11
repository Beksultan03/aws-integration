<table>
    <thead>
    <tr>
        <th>Key Type</th>
        <th>Key</th>
        <th>Vendor</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach($keys as $key)
        <tr>
            <td>{{ $key['key_type'] }}</td>
            <td>{{ $key['key'] }}</td>
            <td>{{ $key['vendor'] }}</td>
            <td>{{ $key['status'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
