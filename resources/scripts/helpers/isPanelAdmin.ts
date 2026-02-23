import { UserData } from '@/state/user';

const normalizeRoleName = (roleName?: string | null): string => String(roleName || '')
    .trim()
    .toLowerCase();

export const isPanelAdmin = (user?: UserData | null): boolean => {
    if (!user) return false;

    const roleName = normalizeRoleName(user.roleName);
    const roleId = typeof user.roleId === 'number' ? user.roleId : null;
    const roleScopesCount = typeof user.roleScopesCount === 'number' ? user.roleScopesCount : null;

    // System/root account must always retain admin surface.
    if (user.rootAdmin || roleName === 'root' || roleId === 1) {
        return true;
    }

    if (roleScopesCount !== null && roleScopesCount <= 0) {
        return false;
    }

    if (roleName !== '') {
        return roleName !== 'user';
    }

    if (roleId !== null) {
        return roleId !== 3;
    }

    return false;
};

export default isPanelAdmin;
