import { authOptions } from '@/app/api/auth/[...nextauth]/auth';
import { google } from 'googleapis';
import { Session, getServerSession } from 'next-auth';

/**
 * A helper function to create a Google Classroom API instance
 *
 * @param session - NextAuth session
 * @returns Google Classroom API instance
 */
const googleClassroom = async () => {
  const session = await getServerSession(authOptions);
  const oauth2Client = new google.auth.OAuth2();
  oauth2Client.setCredentials({ access_token: session?.accessToken });

  return google.classroom({ version: 'v1', auth: oauth2Client });
};

export default googleClassroom;
