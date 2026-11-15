using System.Globalization;

namespace WishList.Converters;

/// <summary>Turns a "#rrggbb" string (e.g. from BoldPop) into a Color.</summary>
public class HexToColorConverter : IValueConverter
{
    public object? Convert(object? value, Type targetType, object? parameter, CultureInfo culture) =>
        value is string hex && !string.IsNullOrWhiteSpace(hex) ? Color.FromArgb(hex) : Colors.Transparent;

    public object? ConvertBack(object? value, Type targetType, object? parameter, CultureInfo culture) =>
        throw new NotSupportedException();
}
