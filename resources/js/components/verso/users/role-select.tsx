import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RoleOption, UserRole } from '@/types';

// Drop down to pick a user role.
export function RoleSelect({
    id,
    roles,
    value,
    onChange,
}: {
    id: string;
    roles: RoleOption[];
    value: UserRole;
    onChange: (role: UserRole) => void;
}) {
    return (
        <Select
            value={value}
            onValueChange={(role) => onChange(role as UserRole)}
        >
            <SelectTrigger id={id}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {roles.map((role) => (
                    <SelectItem key={role.value} value={role.value}>
                        {role.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
