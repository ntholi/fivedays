import { classroom_v1 } from 'googleapis';
import { Text, ScrollArea, Flex, Card, Button } from '@mantine/core';
import React, { useEffect } from 'react';
import axios from 'axios';
import { IconWand } from '@tabler/icons-react';
import GraderItem from './GraderItem';
import { axiosInstance } from '@/lib/config/axios';
import { useSession } from 'next-auth/react';

type Props = {
  rubric: Rubric[];
  courseWork: classroom_v1.Schema$CourseWork;
  submission: StudentSubmission;
  courseId: string;
  setSubmissions: (
    value:
      | StudentSubmission[]
      | ((prevVar: StudentSubmission[]) => StudentSubmission[])
  ) => void;
};

export interface Grade {
  title: string;
  points: number;
  comment: string;
}

export default function GraderPanel({
  rubric,
  courseWork,
  submission,
  courseId,
  setSubmissions,
}: Props) {
  const [totalPoints, setTotalPoints] = React.useState(0);
  const [grades, setGrades] = React.useState<Grade[]>([]);
  const [loading, setLoading] = React.useState(false);
  const maxPoints = rubric.reduce((sum, it) => sum + it.points, 0);
  const { data: session } = useSession();

  useEffect(() => {
    const newGrade = grades.reduce((sum, grade) => sum + grade.points, 0);
    setSubmissions((prev) => {
      const update = [...prev];
      const index = update.findIndex((it) => it.id === submission.id);
      if (index !== -1) {
        update[index] = {
          ...update[index],
          draftGrade: newGrade,
        };
      }
      return update;
    });
  }, [grades]);

  async function doAutoGrading() {
    const fileId = submission?.attachments?.at(0)?.id;
    try {
      setLoading(true);
      const response = await axios.get('/api/grader', {
        params: {
          fileId: fileId,
          questionId: courseWork.id,
          question: courseWork.description,
        },
      });
      const newGradesResponse = await axios.post('/api/assigngrades', {
        params: {
          courseId: courseId,
          courseWorkId: courseWork.id,
          studentSubmissionId: submission.id,
          grade: totalPoints,
        },
      });
      console.log(newGradesResponse);
      setGrades(response.data);
    } catch (e) {
      console.log(e);
    }
    setLoading(false);
  }

  return (
    <>
      <Flex justify='flex-end' mr='md' mb='sm'>
        <Button
          leftIcon={<IconWand size='1.2rem' />}
          variant='gradient'
          gradient={{ from: 'indigo', to: 'cyan' }}
          onClick={doAutoGrading}
          loading={loading}
        >
          Grade
        </Button>
      </Flex>
      <Card withBorder mb='md' mr='md' shadow='xs'>
        <Flex justify='space-between'>
          <Text fw='bold'>Points</Text>
          <Text fw='bold'>
            {totalPoints}/{maxPoints}
          </Text>
        </Flex>
      </Card>
      <ScrollArea h='90vh' pr='md'>
        {rubric.map((it) => (
          <GraderItem
            grades={grades}
            setGrades={setGrades}
            key={it.title}
            rubric={it}
            setTotalPoints={setTotalPoints}
          />
        ))}
      </ScrollArea>
    </>
  );
}
