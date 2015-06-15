Validation.add('validate-iban-number', 'Invalid IBAN number', function(v) {
    var remainder = prepareIban(v),
        block;

    while (remainder.length > 2){
        block = remainder.slice(0, 9);
        remainder = parseInt(block, 10) % 97 + remainder.slice(block.length);
    }

    return (parseInt(remainder, 10) % 97) == 1
});

var A = 'A'.charCodeAt(0),
    Z = 'Z'.charCodeAt(0);

function prepareIban(iban) {
    iban = iban.toUpperCase();
    iban = iban.substr(4) + iban.substr(0,4);

    return iban.split('').map(function(n){
        var code = n.charCodeAt(0);
        if (code >= A && code <= Z){
            return code - A + 10;
        } else {
            return n;
        }
    }).join('');
}
