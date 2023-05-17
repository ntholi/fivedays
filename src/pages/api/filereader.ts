import { NextApiRequest, NextApiResponse } from 'next';
import { getSession } from 'next-auth/react';
import axios from 'axios';
import { googleDrive } from '@/lib/helpers/googleClassroom';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const session = await getSession({ req });
  if (!session) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  const fileId = req.query.fileId;
  if (!fileId) {
    return res.status(400).json({ error: 'Missing "fileId" query parameter.' });
  }

  const drive = googleDrive(session);
  const fileUrl = `https://www.googleapis.com/drive/v3/files/${fileId}?alt=media`;
  try {
    const response = await axios.get(fileUrl, {
      headers: { Authorization: `Bearer ${session.accessToken}` },
      responseType: 'text',
    });
    res.status(200).send(response.data);
  } catch (err: any) {
    console.error(err);

    if (err.response) {
      console.log(err.response.data);
      console.log(err.response.status);
      console.log(err.response.headers);
    } else if (err.request) {
      console.log(err.request);
    } else {
      console.log('Error', err.message);
    }

    res.status(500).json({ error: 'Error reading file' });
  }
}
