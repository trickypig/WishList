using System.Globalization;

namespace WishList.Converters;

public class PriceConverter : IValueConverter
{
    public object? Convert(object? value, Type targetType, object? parameter, CultureInfo culture)
    {
        if (value is decimal price)
            return price.ToString("C2");
        return string.Empty;
    }

    public object? ConvertBack(object? value, Type targetType, object? parameter, CultureInfo culture)
    {
        if (value is string str && decimal.TryParse(str, NumberStyles.Currency, culture, out var result))
            return result;
        return null;
    }
}
