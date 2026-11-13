import type { NavigateFunction } from 'react-router-dom';
import { acceptInvite } from '../api/client';

/** Query string that carries an invite through /login and /register. */
export function inviteQuery(token: string, email: string): string {
  return '?' + new URLSearchParams({ invite: token, email }).toString();
}

/** Reads the invite token and prefill email that /invite passed to /login or /register. */
export function readInviteParams(search: string): { invite: string; email: string } {
  const params = new URLSearchParams(search);
  return { invite: params.get('invite') ?? '', email: params.get('email') ?? '' };
}

/**
 * Joins the invite's family as the signed-in user and lands on the family page.
 * Returns the error message if the link could not be used, without navigating.
 */
export async function finishInvite(token: string, navigate: NavigateFunction): Promise<string | null> {
  try {
    const { family, already_member } = await acceptInvite(token);
    const message = already_member
      ? `You're already a member of ${family.name}`
      : `You've joined ${family.name}`;
    navigate(`/families/${family.id}`, { replace: true, state: { message } });
    return null;
  } catch (err: unknown) {
    return err instanceof Error ? err.message : 'Could not join the family';
  }
}

/** After signing in or registering from an invite: join, or go back to /invite to show why not. */
export async function finishInviteAfterAuth(token: string, navigate: NavigateFunction): Promise<void> {
  const error = await finishInvite(token, navigate);
  if (error !== null) {
    navigate(`/invite?token=${encodeURIComponent(token)}`, { replace: true });
  }
}
