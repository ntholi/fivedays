import NextAuth, { NextAuthOptions } from 'next-auth';
import GoogleProvider from 'next-auth/providers/google';
import axios from 'axios';

const authOptions: NextAuthOptions = {
  providers: [
    GoogleProvider({
      clientId: process.env.GOOGLE_CLIENT_ID || '',
      clientSecret: process.env.GOOGLE_CLIENT_SECRET || '',
      authorization: {
        url: 'https://accounts.google.com/o/oauth2/v2/auth',
        params: {
          prompt: 'consent',
          access_type: 'offline',
          response_type: 'code',

          scope:
            'email openid profile https://www.googleapis.com/auth/classroom.courses.readonly https://www.googleapis.com/auth/classroom.coursework.me https://www.googleapis.com/auth/classroom.coursework.students https://www.googleapis.com/auth/classroom.profile.emails https://www.googleapis.com/auth/classroom.profile.photos https://www.googleapis.com/auth/classroom.student-submissions.me.readonly https://www.googleapis.com/auth/classroom.student-submissions.students.readonly https://www.googleapis.com/auth/classroom.topics https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/drive.appdata https://www.googleapis.com/auth/drive.appfolder https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive.resource https://www.googleapis.com/auth/drive.metadata https://www.googleapis.com/auth/drive.metadata.readonly https://www.googleapis.com/auth/drive.readonly.metadata https://www.googleapis.com/auth/drive.photos.readonly',
        },
      },
    }),
  ],
  secret: process.env.JWT_SECRET,
  callbacks: {
    async jwt({ token, account }) {
      if (account) {
        token = Object.assign({}, token, {
          accessToken: account.access_token,
          refreshToken: account.refresh_token,
          accessTokenExpires: Date.now() + account.expires_in * 1000,
        });
      } else if (Date.now() > token.accessTokenExpires) {
        // accessToken has expired, try to refresh it
        const refreshTokenResponse = await refreshAccessToken(
          token.refreshToken
        );
        if (refreshTokenResponse.access_token) {
          token = Object.assign({}, token, {
            accessToken: refreshTokenResponse.access_token,
            refreshToken: refreshTokenResponse.refresh_token
              ? refreshTokenResponse.refresh_token
              : token.refreshToken, // new refresh token is only provided if the old one has expired
            accessTokenExpires:
              Date.now() + refreshTokenResponse.expires_in * 1000,
          });
        } else {
          console.log(
            'Failed to refresh access token: ' +
              JSON.stringify(refreshTokenResponse)
          );
        }
      }
      return token;
    },
    async session({ session, token }) {
      if (session) {
        session = Object.assign({}, session, {
          accessToken: token.accessToken,
          refreshToken: token.refreshToken,
          accessTokenExpires: token.accessTokenExpires,
        });
      }
      return session;
    },
  },
};

async function refreshAccessToken(refreshToken: any) {
  try {
    const { data } = await axios({
      method: 'post',
      url: 'https://oauth2.googleapis.com/token',
      params: {
        client_id: process.env.GOOGLE_CLIENT_ID,
        client_secret: process.env.GOOGLE_CLIENT_SECRET,
        grant_type: 'refresh_token',
        refresh_token: refreshToken,
      },
    });

    return data;
  } catch (error: any) {
    console.log('Error refreshing access token: ', error.response.data);
    return error.response.data;
  }
}

export default NextAuth(authOptions);
