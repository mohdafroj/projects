import { HttpClient } from '@angular/common/http';
import { Injectable, computed, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { AuthResponse, SessionUser } from './models';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly userState = signal<SessionUser | null>(this.readUser());
  readonly user = this.userState.asReadonly();
  readonly isAuthenticated = computed(() => this.userState() !== null);
  readonly canManageEmployees = computed(() => {
    const role = this.userState()?.role;
    return role === 'admin' || role === 'hr';
  });

  constructor(private readonly http: HttpClient) {}

  login(email: string, password: string): Observable<AuthResponse> {
    return this.http.post<AuthResponse>('/api/auth/login', { email, password }).pipe(
      tap((session) => {
        localStorage.setItem('employee-token', session.token);
        localStorage.setItem('employee-user', JSON.stringify(session.user));
        this.userState.set(session.user);
      })
    );
  }

  logout(): Observable<void> {
    return this.http.post<void>('/api/auth/logout', {}).pipe(
      tap(() => this.clearSession())
    );
  }

  clearSession(): void {
    localStorage.removeItem('employee-token');
    localStorage.removeItem('employee-user');
    this.userState.set(null);
  }

  private readUser(): SessionUser | null {
    try {
      const rawUser = localStorage.getItem('employee-user');
      return rawUser ? JSON.parse(rawUser) as SessionUser : null;
    } catch {
      localStorage.removeItem('employee-user');
      localStorage.removeItem('employee-token');
      return null;
    }
  }
}
